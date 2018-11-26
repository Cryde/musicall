export default create;

function create({type, message}) {
  const faClass = type === 'success' ? 'fa-check-circle-o' : 'fa-exclamation-circle';
  const cssType = type === 'success' ? 'success' : 'error';

  return `
    <div class="alert ${cssType} publication-edit">
        <i class="fa ${faClass}"></i> <span><div class="close">❌</div>${message}</span>
    </div>
  `;
}