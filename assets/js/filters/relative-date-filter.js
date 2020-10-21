import {format, isThisYear, parseISO} from 'date-fns';
import distanceInWordsToNow from 'date-fns/formatDistanceToNow';
import differenceInDays from 'date-fns/differenceInDays';
import {fr} from 'date-fns/locale'

export default relativeDateFilter;

function relativeDateFilter(value, options = {}) {
  const {differenceLimit = 2, showHours = true} = options;
  const parsedDate = parseISO(value);
  const difference = differenceInDays(new Date, parsedDate);

  if (difference > differenceLimit) {
    if (isThisYear(parsedDate)) {
      return format(parsedDate, `dd MMMM ${showHours ? 'HH:mm' : ''}`, {locale: fr});
    }
    return format(parsedDate, `dd MMMM yyyy${showHours ? ', HH:mm' : ''}`, {locale: fr});
  }

  return distanceInWordsToNow(parsedDate, {locale: fr, addSuffix: true});
}