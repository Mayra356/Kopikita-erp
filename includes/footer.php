<?php
/**
 * KopiKita ERP - Footer Component
 */
?>
        </main> <!-- /page-body -->
    </div> <!-- /app-main -->
</div> <!-- /app-container -->

<!-- Bootstrap 5.3 Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Custom App JS -->
<script src="assets/js/app.js"></script>

<!-- Mobile Sidebar Toggle -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const toggleBtn = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('appSidebar');
    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', function () {
            sidebar.classList.toggle('show');
        });
    }
});
</script>

</body>
</html>
