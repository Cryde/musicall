/**
 * The `@tous` sentinel of the Band Space chat.
 *
 * It rides the roster as a pretend member, so it is suggested, picked and chipped like anybody else,
 * and the hint travels with it rather than as a prop, which keeps MentionEditor ignorant of what
 * "tous" means. Shared by the composer and the edit box rather than declared in each: the id has to
 * match ChatMentionResolver::EVERYONE_TOKEN on the server, and a second copy is a second thing to
 * forget.
 */
export const EVERYONE_MENTION_ID = 'tous'

export const EVERYONE_MEMBER = {
  user_id: EVERYONE_MENTION_ID,
  username: EVERYONE_MENTION_ID,
  hint: 'tout le groupe'
}
