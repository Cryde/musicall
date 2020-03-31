import {parseISO, format} from 'date-fns';
import distanceInWordsToNow from 'date-fns/formatDistanceToNow';
import differenceInDays from 'date-fns/differenceInDays';
import { fr } from 'date-fns/locale'


export default relativeDateFilter;

function relativeDateFilter(value) {
  const parsedDate = parseISO(value);
  const difference = differenceInDays(new Date, parsedDate);

  if(difference > 2) {
    return format(parsedDate, 'dd MMMM yyyy, HH:mm', {locale: fr});
  }

  return distanceInWordsToNow(parsedDate, {locale: fr, addSuffix: true});
}