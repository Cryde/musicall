// One file per area like fr/, loaded on demand by setLocale(). A key it lacks shows in French.

import auth from './auth.json' with { type: 'json' }
import bandSpace from './band_space.json' with { type: 'json' }
import bandSpaceActivity from './band_space_activity.json' with { type: 'json' }
import bandSpaceAgenda from './band_space_agenda.json' with { type: 'json' }
import bandSpaceChat from './band_space_chat.json' with { type: 'json' }
import bandSpaceFiles from './band_space_files.json' with { type: 'json' }
import bandSpaceFinance from './band_space_finance.json' with { type: 'json' }
import bandSpaceNotes from './band_space_notes.json' with { type: 'json' }
import bandSpaceSetlist from './band_space_setlist.json' with { type: 'json' }
import bandSpaceTasks from './band_space_tasks.json' with { type: 'json' }
import bandSpaceTechRider from './band_space_tech_rider.json' with { type: 'json' }
import errors from './errors.json' with { type: 'json' }
import forum from './forum.json' with { type: 'json' }
import home from './home.json' with { type: 'json' }
import layout from './layout.json' with { type: 'json' }
import message from './message.json' with { type: 'json' }
import notification from './notification.json' with { type: 'json' }
import profile from './profile.json' with { type: 'json' }
import publication from './publication.json' with { type: 'json' }
import search from './search.json' with { type: 'json' }
import shared from './shared.json' with { type: 'json' }

export default {
  ...layout,
  auth,
  band_space: bandSpace,
  band_space_activity: bandSpaceActivity,
  band_space_agenda: bandSpaceAgenda,
  band_space_chat: bandSpaceChat,
  band_space_files: bandSpaceFiles,
  band_space_finance: bandSpaceFinance,
  band_space_notes: bandSpaceNotes,
  band_space_setlist: bandSpaceSetlist,
  band_space_tasks: bandSpaceTasks,
  band_space_tech_rider: bandSpaceTechRider,
  errors,
  forum,
  home,
  message,
  notification,
  profile,
  publication,
  search,
  shared
}
