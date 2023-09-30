var modal = document.getElementById("myModal");

// Get the <span> element that closes the modal
var span = document.getElementById("closeBtn");

// Function to open the modal with dynamic data
function openModal(name, email, phone) {
  // Update the modal content with the provided data
  document.getElementById("userName").textContent = name;
  document.getElementById("userEmail").textContent = email;
  document.getElementById("userPhone").textContent = phone;

  // Display the modal
  modal.style.display = "block";
}

// When the user clicks on <span> (x), close the modal
span.onclick = function() {
  modal.style.display = "none";
}

// When the user clicks anywhere outside of the modal, close it
window.onclick = function(event) {
  if (event.target == modal) {
    modal.style.display = "none";
  }
}