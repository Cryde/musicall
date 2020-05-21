import {parseISO, format} from 'date-fns';
import { fr } from 'date-fns/locale'

export default prettyDateFilter;

function prettyDateFilter(value) {
  const parsedDate = parseISO(value);

  return format(parsedDate, 'dd MMMM yyyy, HH:mm', {locale: fr});
}