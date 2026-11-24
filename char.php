<!-- QR Code Format Modal -->
<div class="modal fade" id="qrCodeModal" tabindex="-1" aria-labelledby="qrCodeModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="qrCodeModalLabel">
          <i class="fas fa-qrcode"></i> QR Code Format
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-info">
          <strong>QR Code Format</strong><br>
          When creating QR codes for employees, use this format:<br><br>
          <code>EMP-XXX|First Name|Last Name|Position</code>
          <br><br>
          <strong>Example:</strong><br>
          <code>EMP-001|John|Doe|Tanod</code>
        </div>

        <ul>
          <li>Use the exact format shown above</li>
          <li>Separate fields with the pipe character <code>|</code></li>
          <li>Employee ID must start with "EMP-"</li>
          <li>QR codes must be generated manually</li>
        </ul>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
