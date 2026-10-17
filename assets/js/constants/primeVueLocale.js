/**
 * The calendar part of PrimeVue's locale, per app locale. PrimeVue deep merges it into its English
 * defaults, so every DatePicker starts its weeks on Monday and names its days and months in the
 * language, like the agenda (FullCalendar `firstDay: 1`). Without it the pickers started on Sunday
 * and read "Su Mo Tu", "January".
 *
 * Only the keys a DatePicker reads: the rest of PrimeVue's wording is set per component. Keyed by
 * app locale, picked with `forActiveLocale()`. Both entries carry the same keys, so switching from
 * one to the other replaces every one of them.
 */
const FRENCH = Object.freeze({
  firstDayOfWeek: 1,
  dateFormat: 'dd/mm/yy',
  dayNames: ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'],
  dayNamesShort: ['dim.', 'lun.', 'mar.', 'mer.', 'jeu.', 'ven.', 'sam.'],
  dayNamesMin: ['D', 'L', 'M', 'M', 'J', 'V', 'S'],
  monthNames: [
    'janvier',
    'février',
    'mars',
    'avril',
    'mai',
    'juin',
    'juillet',
    'août',
    'septembre',
    'octobre',
    'novembre',
    'décembre'
  ],
  monthNamesShort: [
    'janv.',
    'févr.',
    'mars',
    'avr.',
    'mai',
    'juin',
    'juil.',
    'août',
    'sept.',
    'oct.',
    'nov.',
    'déc.'
  ],
  today: "Aujourd'hui",
  clear: 'Effacer',
  weekHeader: 'Sem.',
  chooseYear: "Choisir l'année",
  chooseMonth: 'Choisir le mois',
  chooseDate: 'Choisir la date',
  prevDecade: 'Décennie précédente',
  nextDecade: 'Décennie suivante',
  prevYear: 'Année précédente',
  nextYear: 'Année suivante',
  prevMonth: 'Mois précédent',
  nextMonth: 'Mois suivant',
  prevHour: 'Heure précédente',
  nextHour: 'Heure suivante',
  prevMinute: 'Minute précédente',
  nextMinute: 'Minute suivante',
  prevSecond: 'Seconde précédente',
  nextSecond: 'Seconde suivante'
})

// British English: Monday first and the day before the month, unlike PrimeVue's American defaults.
const ENGLISH = Object.freeze({
  firstDayOfWeek: 1,
  dateFormat: 'dd/mm/yy',
  dayNames: ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
  dayNamesShort: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
  dayNamesMin: ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'],
  monthNames: [
    'January',
    'February',
    'March',
    'April',
    'May',
    'June',
    'July',
    'August',
    'September',
    'October',
    'November',
    'December'
  ],
  monthNamesShort: [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'May',
    'Jun',
    'Jul',
    'Aug',
    'Sep',
    'Oct',
    'Nov',
    'Dec'
  ],
  today: 'Today',
  clear: 'Clear',
  weekHeader: 'Wk',
  chooseYear: 'Choose year',
  chooseMonth: 'Choose month',
  chooseDate: 'Choose date',
  prevDecade: 'Previous decade',
  nextDecade: 'Next decade',
  prevYear: 'Previous year',
  nextYear: 'Next year',
  prevMonth: 'Previous month',
  nextMonth: 'Next month',
  prevHour: 'Previous hour',
  nextHour: 'Next hour',
  prevMinute: 'Previous minute',
  nextMinute: 'Next minute',
  prevSecond: 'Previous second',
  nextSecond: 'Next second'
})

export const PRIME_VUE_CALENDAR_LOCALES = Object.freeze({ fr: FRENCH, en: ENGLISH })
