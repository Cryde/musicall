/**
 * The calendar part of PrimeVue's locale, in French. PrimeVue deep merges it into its English
 * defaults, so every DatePicker starts its weeks on Monday and names its days and months in French,
 * like the agenda (FullCalendar `firstDay: 1`). Without it the pickers started on Sunday and read
 * « Su Mo Tu », « January ».
 *
 * Only the keys a DatePicker reads: the rest of PrimeVue's wording is set per component.
 */
export const PRIME_VUE_CALENDAR_LOCALE = Object.freeze({
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
