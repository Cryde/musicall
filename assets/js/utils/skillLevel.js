/** How many of the four bars a musician's level fills, as the profile and the teacher page draw it. */
const SKILL_STEPS = { beginner: 1, intermediate: 2, advanced: 3, professional: 4 }

export const SKILL_STEP_COUNT = 4

export function skillSteps(level) {
  return SKILL_STEPS[level] ?? 0
}
