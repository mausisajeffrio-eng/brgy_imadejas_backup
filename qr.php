<!DOCTYPE html>
<html>
<head>
  <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/webrtc-adapter/3.3.3/adapter.min.js"></script>
  <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/vue/2.1.10/vue.min.js"></script>
    <script type="text/javascript" src="https://cdn.jsdelivr.net/gh/schmich/instascan-builds@master/instascan.min.js"></script>
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
</head>
<body>
  <div class="container">
    <div class="row">
      <div class="col-md-6">
        <video id="preview" width="100%"></video>
      </div>
      <div class="col-md-6">
        <form action="attendance.php" method="post" class="form-horizontal"> 
            <div class="form-group">
                <label>Attendance Type</label>
                <select name="type" id="attendanceType" class="form-control" required onchange="updateForm()">
                    <option value="daily" selected>Daily Check-in</option>
                    <option value="event">Event</option>
                    <option value="disaster_response">Disaster Response</option>
                </select>
            </div>
            <div id="detailsGroup" class="form-group" style="display: none;">
                <label id="detailsLabel">Event Details</label>
                <input type="text" name="details" id="attendanceDetails" class="form-control" placeholder="Enter details">
            </div>
            <div class="form-group">
                <label>SCAN QR CODE</label>
                <input type="text" name="text" id="text" readonly placeholder="Scan QR Code" class="form-control">
                <input type="hidden" name="type" id="selectedType" value="daily">
                <input type="hidden" name="details" id="hiddenDetails" value="Regular Check-in">
            </div>
        </form>
      </div>
    </div>

    <script>
      let scanner = new Instascan.Scanner({ video: document.getElementById('preview')});
      
      // Single scanner listener to handle all QR code scans
      scanner.addListener('scan', function(content) {
          console.log('QR Code scanned:', content);
          
          const type = document.getElementById('attendanceType').value;
          const details = document.getElementById('attendanceDetails');
          const selectedType = document.getElementById('selectedType');
          const hiddenDetails = document.getElementById('hiddenDetails');
          
          console.log('Processing attendance type:', type);
          
          // Validate QR code format first
          if (!content || content.trim() === '') {
              console.error('Invalid QR code content');
              alert('Invalid QR code. Please try again.');
              return;
          }
          
          // Set the type and details based on selection
          selectedType.value = type;
          
          if (type === 'daily') {
              hiddenDetails.value = 'Regular Check-in';
              console.log('Processing daily check-in');
          } 
          else if (type === 'event' || type === 'disaster_response') {
              // For event/disaster, ensure details are entered before submitting
              if (!details.value || details.value.trim() === '') {
                  const detailType = type === 'event' ? 'event' : 'disaster';
                  console.error('Missing details for', detailType);
                  alert(`Please enter ${detailType} details before scanning`);
                  details.focus();
                  return;
              }
              hiddenDetails.value = details.value.trim();
              console.log('Processing', type, 'with details:', hiddenDetails.value);
          }
          
          // Set the QR code value
          document.getElementById('text').value = content;
          
          // Log form data before submission
          const formData = new FormData(document.forms[0]);
          console.log('Form data to be submitted:');
          for (let [key, value] of formData.entries()) {
              console.log(key + ': ' + value);
          }
          
          console.log('Submitting form with type:', selectedType.value, 'and details:', hiddenDetails.value);
          document.forms[0].submit();
      });
      
      // Initialize camera
      Instascan.Camera.getCameras().then(function (cameras) {
        if (cameras.length > 0) {
          // Try to use back camera first, fall back to any available camera
          const backCamera = cameras.find(camera => camera.name.toLowerCase().includes('back'));
          scanner.start(backCamera || cameras[0]);
          console.log('Camera started successfully');
        } else {
          const errorMsg = 'No cameras found. Please check your camera permissions.';
          console.error(errorMsg);
          alert(errorMsg);
        }
      }).catch(function(e) {
        const errorMsg = 'Error accessing camera: ' + (e.message || 'Unknown error');
        console.error('Camera error:', e);
        alert(errorMsg);
      });
          const type = document.getElementById('attendanceType').value;
          const details = document.getElementById('attendanceDetails');
          const selectedType = document.getElementById('selectedType');
         
          
          console.log('QR Code scanned:', c);
          console.log('Selected type:', type);
          console.log('Details input:', details.value);
          
          // Set the type and details based on selection
          selectedType.value = type;
          
          if (type === 'daily') {
              hiddenDetails.value = 'Regular Check-in';
              console.log('Setting daily check-in with Regular Check-in details');
          } else if (type === 'event' || type === 'disaster_response') {
              // For event/disaster, ensure details are entered before submitting
              if (!details.value || details.value.trim() === '') {
                  alert('Please enter event/disaster details first');
                  details.focus();
                  return;
              }
              hiddenDetails.value = details.value.trim();
              console.log('Setting', type, 'with details:', hiddenDetails.value);
          }
          
          // Log the form data before submission
          const formData = new FormData(document.forms[0]);
          console.log('Form data to be submitted:');
          for (let [key, value] of formData.entries()) {
              console.log(key + ': ' + value);
          }
          
          document.getElementById("text").value = c;
          console.log('Submitting form...');
          document.forms[0].submit(); 
      
      // Handle attendance type change
      const attendanceType = document.getElementById('attendanceType');
      const detailsGroup = document.getElementById('detailsGroup');
      const attendanceDetails = document.getElementById('attendanceDetails');
      const hiddenDetails = document.getElementById('hiddenDetails');
      
      // Update form based on attendance type
      function updateForm() {
          const type = attendanceType.value;
          const detailsLabel = document.getElementById('detailsLabel');
          const detailsInput = document.getElementById('attendanceDetails');
          
          // Update the hidden input that gets submitted
          document.getElementById('selectedType').value = type;
          
          if (type === 'event' || type === 'disaster_response') {
              detailsGroup.style.display = 'block';
              attendanceDetails.required = true;
              hiddenDetails.disabled = true;
              
              // Update label and placeholder based on type
              if (type === 'event') {
                  detailsLabel.textContent = 'Event Details';
                  detailsInput.placeholder = 'Enter event name or description';
              } else {
                  detailsLabel.textContent = 'Disaster Response Details';
                  detailsInput.placeholder = 'Enter disaster type and location (e.g., Typhoon in Barangay 123)';
              }
              
              // Disable QR scanning until details are entered
              document.getElementById('text').disabled = true;
              document.getElementById('text').placeholder = 'Enter details first';
              
              // Clear previous details when switching to event/disaster
              if (hiddenDetails.value === 'Regular Check-in') {
                  attendanceDetails.value = '';
              }
          } else {
              // For daily check-in
              detailsGroup.style.display = 'none';
              attendanceDetails.required = false;
              hiddenDetails.disabled = false;
              document.getElementById('text').disabled = false;
              document.getElementById('text').placeholder = 'Scan QR Code';
              hiddenDetails.value = 'Regular Check-in';
              attendanceDetails.value = '';
          }
          
          console.log('Form updated for type:', type);
      }
      
      // Update form when type changes
      attendanceType.addEventListener('change', updateForm);
      
      // Update hidden details when user types and enable/disable QR scanning
      attendanceDetails.addEventListener('input', function() {
          const details = this.value.trim();
          hiddenDetails.value = details || 'No details provided';
          // Enable QR scanning only if details are provided for event/disaster
          if (details !== '') {
              document.getElementById('text').disabled = false;
              document.getElementById('text').placeholder = 'Scan QR Code';
          } else {
              document.getElementById('text').disabled = true;
              document.getElementById('text').placeholder = 'Enter details first';
          }
      });
      
      // Initialize form state
      updateForm();
    </script>
  </body>
</html>
