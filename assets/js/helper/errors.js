export {retrieveErrors};

function retrieveErrors(errors) {
  return errors.violations.map((violation) => {
    return violation.title;
  });
}