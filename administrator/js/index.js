const bars = document.querySelector('.hamberger');
const container = document.querySelector('.side-nav-container');
const page = document.querySelector('.page-content');

bars.addEventListener('click', ()=>{
    container.classList.toggle('open');
    page.classList.toggle('pageopen');
});

document.getElementById('sendNotificationForm').addEventListener('submit', function (e) {
    e.preventDefault(); // Prevent default form submission

    // Collect form data
    const formData = new FormData(this);

    // Send a POST request to the PHP script
    fetch('sendNotification.php', {
      method: 'POST',
      "Content-Type": "application/json",
      body: formData,
    })
      .then(response => response.text()) // Assuming the PHP script returns JSON
      .then(data => {
        console.log(data);
        // Update the status message in the <p> tag
        // const statusIndicator = document.getElementById('statusIndicator');

        // if (data.status === 'success') {
        //   statusIndicator.textContent = 'Notification sent successfully.';
        //   statusIndicator.style.color = 'green';
        // } else {
        //   statusIndicator.textContent = 'Failed to send notification.';
        //   statusIndicator.style.color = 'red';
        // }

      })
      .catch(error => {
        // Handle any errors here
        console.error('Error:', error);
      });
  });

function deleteUser(id, type, userName) {
    alert(`Caution: Deleting ${userName}'s account may result in massive data loss. This operation is considered high-risk and should only be performed by authorized personnel. If you believe this action is necessary, please contact the system administrator or support team for assistance. (Emmanuel Muswalo: emuswalo7@gmail.com)`);
    // // Display a confirmation dialog
    // const confirmation = confirm(`Are you sure you want to delete ${userName}?`);
    
    // // Check if the user confirmed the deletion
    // if (confirmation) {
    //     // Define the URL of your PHP endpoint
    //     const url = 'delete.php';

    //     // Create a data object to send in the request body
    //     const data = JSON.stringify({ id, type });

    //     // Define the fetch options (method, headers, body)
    //     const options = {
    //         method: 'POST', 
    //         headers: {
    //             'Content-Type': 'application/json',
    //         },
    //         body: data,
    //     };

    //     // Send the fetch request
    //     fetch(url, options)
    //         .then(response => response.text())
    //         .then(data => {
    //             console.log(data)
    //             // if (data.status === true) {
    //             //     alert('User deleted successfully.');
    //             //     // You can perform additional actions here if needed
    //             // } else {
    //             //     alert('Failed to delete user.');
    //             // }
    //         })
    //         .catch(error => {
    //             console.error('Error:', error);
    //         });
    // }
}
