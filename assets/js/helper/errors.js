export {retrieveErrors};

function retrieveErrors(errors) {
  console.log(errors);

  return errors.violations.map((violation) => {
    return violation.title;
  });
}