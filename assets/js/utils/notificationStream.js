import { reconnectDelay } from './realtimeBackoff.js'

/**
 * The live notification stream: one `EventSource` on the signed-in user's own Mercure topic.
 *
 * Lives here rather than inside the Pinia store so that the lifecycle, which is the part with real
 * failure modes, can be driven by a test with a fake `EventSource` and fake timers. The store keeps
 * the state; this keeps the connection.
 *
 * The server publishes a tag, not the thing that changed, so the body is only ever a discriminator:
 * this parses it and hands it over, and the refetching is the store's job.
 *
 * Topics are a list because Mercure multiplexes: one connection carries as many `topic=` parameters
 * as you give it, and opening a second EventSource per topic would cost a second hub subscriber and
 * a second reconnect on every deploy for nothing. Today the list holds one entry, because the
 * subscriber cookie authorizes exactly one topic and a topic the token does not name receives
 * nothing at all. When the cookie widens to a member's band spaces, the caller passes a longer list
 * and has to `disconnect()` first: the topics are fixed in the URL, so a longer list cannot take
 * effect on a stream that is already open. Two things worth knowing at that point. The SSE frame
 * carries only an id and data, never the topic that matched, so the payload's own `type` is what
 * tells the client what changed. And a subscriber has to *ask* for a topic, not merely hold a token
 * that authorizes it.
 */

/**
 * Must match `App\Mercure\MercureTopic::userNotifications()`. The two are the same contract written
 * in two languages, and a rename on either side delivers nothing while erroring nowhere.
 */
export function notificationTopic(userId) {
  return `/users/${userId}/notifications`
}

/**
 * A body we cannot read becomes null, which callers treat as "refetch everything". Refetching too
 * much is the safe failure here, and throwing out of onmessage would take the handler with it.
 */
function parsePayload(data) {
  try {
    const parsed = JSON.parse(data)

    return parsed !== null && typeof parsed === 'object' ? parsed : null
  } catch {
    return null
  }
}

/** Path of the hub, fixed by the Mercure protocol and by `public_url` in config/packages/mercure.yaml. */
const HUB_PATH = '/.well-known/mercure'

/** How many consecutive failures between attempts at renewing credentials. */
const FAILURES_PER_AUTH_REFRESH = 3

/**
 * @param {object} deps
 * @param {() => string[]} deps.getTopics the topics to subscribe to, read at connect time rather
 *   than captured, because they depend on a profile that arrives after authentication does
 * @param {(payload: object | null) => void} deps.onSignal something changed, go and refetch. The
 *   payload is the update's own body, or null when we cannot know what changed: on reconnect, and on
 *   a body that does not parse. Null means refetch everything.
 * @param {() => Promise<unknown>} deps.onAuthRefreshNeeded renew credentials, best effort
 * @param {(url: string) => EventSource} [deps.openStream] injected for tests
 * @param {{ set: Function, clear: Function }} [deps.timers] injected for tests
 * @param {(attempt: number) => number} [deps.delayFor] injected for tests
 */
export function createNotificationStream({
  getTopics,
  onSignal,
  onAuthRefreshNeeded,
  openStream = (url) => new EventSource(url),
  timers = { set: setTimeout, clear: clearTimeout },
  delayFor = reconnectDelay
}) {
  let stream = null
  let retryHandle = null
  let failures = 0
  // Bumped by every disconnect, so an error event still queued against a stream we have already
  // closed cannot count as a failure or schedule a reconnect nobody asked for.
  let generation = 0

  function connect() {
    if (stream !== null || retryHandle !== null) {
      return
    }

    const topics = getTopics()
    if (topics.length === 0) {
      // Authentication lands one HTTP round trip before the profile does, so the first call from a
      // mounting component can arrive with nothing to subscribe to. The caller watches the profile
      // and calls back.
      return
    }

    const currentGeneration = generation
    const query = topics.map((topic) => `topic=${encodeURIComponent(topic)}`).join('&')
    stream = openStream(`${HUB_PATH}?${query}`)

    stream.onopen = () => {
      // Anything published while the stream was down is simply gone. `Last-Event-ID` belongs to the
      // EventSource object and every retry here builds a new one, so the hub replays nothing however
      // much history the transport keeps. Refetching on recovery is what closes that gap, and a
      // deploy restart is exactly when it opens, for every subscriber at once.
      if (failures > 0) {
        onSignal(null)
      }
      failures = 0
    }

    stream.onmessage = (event) => {
      onSignal(parsePayload(event.data))
    }

    stream.onerror = () => {
      if (currentGeneration !== generation) {
        return
      }

      // Taking the retry over is not belt and braces, it is the only thing that recovers from the
      // failure that actually happens. EventSource retries a dropped *connection* by itself, but a
      // non-200 "fails the connection": one error event, readyState CLOSED, and per the specification
      // no reconnect at all. An expired subscriber cookie is exactly that, a 401, because the hub
      // runs without `anonymous`. Measured in Chrome to be sure. So a browser that slept past the
      // token refresh would otherwise lose the stream for good, silently, bell still looking healthy.
      disconnect()
      failures += 1

      // Every third failure, not *the* third: a renewal that fails, or that returns without minting
      // a usable cookie, has to be retried or the stream reconnects into the same 401 forever. Every
      // failure would be worse than the poll this protects, since renewing also refetches the profile.
      if (failures % FAILURES_PER_AUTH_REFRESH === 0) {
        // Fired, not awaited. Chaining the reconnect behind it meant a hung refresh request left no
        // stream, no timer and nothing scheduled, which is the same silent death this handler exists
        // to prevent. The reconnect keeps its own schedule and uses whatever cookie is current by then.
        onAuthRefreshNeeded().catch(() => {})
      }

      const scheduledGeneration = generation
      retryHandle = timers.set(
        () => {
          retryHandle = null
          if (scheduledGeneration === generation) {
            connect()
          }
        },
        delayFor(failures - 1)
      )
    }
  }

  function disconnect() {
    generation += 1
    if (stream !== null) {
      stream.close()
      stream = null
    }
    if (retryHandle !== null) {
      timers.clear(retryHandle)
      retryHandle = null
    }
  }

  return { connect, disconnect }
}
