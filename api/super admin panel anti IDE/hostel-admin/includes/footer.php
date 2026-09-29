    </div><!-- /page-content -->

    <!-- Footer -->
    <footer class="footer-custom">
        <div>
            <strong><?= APP_NAME ?></strong> v<?= APP_VERSION ?> &mdash; Production Level Core PHP & MySQL
        </div>
        <div>
            &copy; <?= date('Y') ?> All Rights Reserved. Built with <i class="fas fa-heart text-danger"></i> & Bootstrap 5.
        </div>
    </footer>
</main><!-- /main-content -->
</div><!-- /app-container -->

<!-- jQuery 3.7.1 -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<!-- Bootstrap 5 Bundle JS (with Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- DataTables & Responsive JS -->
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>
<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<!-- Custom Main JS -->
<script src="<?= BASE_URL ?>assets/js/main.js?v=<?= time() ?>"></script>

<?php
// Handle Flash Notifications via SweetAlert2 Toast
$flash = get_flash_message();
if ($flash): 
    $icon = $flash['type'];
    if (!in_array($icon, ['success', 'error', 'warning', 'info'])) {
        $icon = 'info';
    }
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 4000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer);
            toast.addEventListener('mouseleave', Swal.resumeTimer);
        }
    });

    Toast.fire({
        icon: '<?= $icon ?>',
        title: <?= json_encode($flash['message']) ?>
    });
});
</script>
<?php endif; ?>

</body>
</html>
