/**
 * One attempt at saving a note body, plus the rules for when a failed one may be tried again.
 *
 * A note body is written by a two second timer, never by a member pressing anything, so a failure
 * has no natural retry: the text stays on screen looking saved and disappears with the tab. What
 * follows is the only thing that gets it to the server, hence the retries.
 *
 * The revision is what makes retrying safe. Every attempt re-sends the same payload, including the
 * revision the text was written against, so a retry landing after another member has written comes
 * back refused instead of replacing their work. Retrying with a fresh revision would turn this
 * helper into the blind overwrite the revision check was added to remove.
 *
 * Which failures may be retried therefore matters more than how many times:
 *
 * - a refused revision (409) is a decision, not a hiccup. Trying again sends the same refused
 *   document and gets the same answer, so it stops immediately and the caller shows the conflict;
 * - a missing revision (428) means the payload was built wrong. No number of attempts adds the
 *   field, so it stops too;
 * - anything else the server answered (403, 404, 422) is equally settled;
 * - a request that never got an answer, or a 5xx, is the transient case. That one is retried.
 *
 * A retry can be refused as a conflict because the first attempt did reach the server and only its
 * answer was lost. The text is then saved and the member is told it is not, which is the safe way
 * round: the alternative overwrites somebody else's paragraph to spare a wrong warning.
 *
 * Pure on purpose, with no store and no component around it, so the rules are tested without a
 * browser. See noteContentSave.test.js.
 */

/** The revision named by the write is no longer the current one. */
const HTTP_CONFLICT = 409

/** The write named no revision at all. */
const HTTP_PRECONDITION_REQUIRED = 428

const HTTP_SERVER_ERROR = 500

/** Short enough that a blip is invisible, spread out enough to outlast a reconnection. */
export const SAVE_RETRY_DELAYS_MS = [1000, 4000]

/**
 * @param {{status?: number}} error a normalized API error, see handleApiError
 * @returns {{status: 'conflict'|'error', canRetry: boolean}} status is the save status to show
 */
export function classifyNoteSaveFailure(error) {
  const httpStatus = error?.status

  if (httpStatus === HTTP_CONFLICT) {
    return { status: 'conflict', canRetry: false }
  }

  // Spelled out rather than left to the rule below: sending the payload again builds the same
  // payload, so this one is settled for a reason of its own.
  if (httpStatus === HTTP_PRECONDITION_REQUIRED) {
    return { status: 'error', canRetry: false }
  }

  // No status at all means the request never reached an answer: offline, dropped connection, or a
  // page in the middle of being closed.
  if (typeof httpStatus !== 'number') {
    return { status: 'error', canRetry: true }
  }

  return { status: 'error', canRetry: httpStatus >= HTTP_SERVER_ERROR }
}

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms))

/**
 * @param {object} options
 * @param {function(): Promise<object>} options.save one attempt, resolving to the saved note
 * @param {number[]} [options.delaysMs] one delay per retry, so its length is the retry count
 * @param {function(number): Promise<void>} [options.wait] injectable for the tests
 * @returns {Promise<{status: 'saved', note: object}|{status: 'conflict'|'error', error: Error}>}
 */
export async function saveNoteContentWithRetries({
  save,
  delaysMs = SAVE_RETRY_DELAYS_MS,
  wait = sleep
}) {
  let retriesUsed = 0

  for (;;) {
    try {
      return { status: 'saved', note: await save() }
    } catch (error) {
      const failure = classifyNoteSaveFailure(error)
      if (!failure.canRetry || retriesUsed >= delaysMs.length) {
        return { status: failure.status, error }
      }

      await wait(delaysMs[retriesUsed])
      retriesUsed++
    }
  }
}
