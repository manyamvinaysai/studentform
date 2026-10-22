document.addEventListener("DOMContentLoaded", () => {
  const form = document.getElementById("studentForm");
  if (!form) return;

  form.addEventListener("submit", function (event) {
    const name = document.getElementById("name").value.trim();
    const phone = document.getElementById("phone").value.trim();

    if (name.length < 2) {
      event.preventDefault();
      alert("Please enter at least 2 characters for your name.");
      return;
    }

    if (!/^[0-9]{10}$/.test(phone)) {
      event.preventDefault();
      alert("Please enter a valid 10-digit phone number.");
    }
  });
});
