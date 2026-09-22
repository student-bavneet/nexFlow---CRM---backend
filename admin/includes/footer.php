<?php
// Shared Footer Partial
?>
</div> <!-- End app-wrapper -->

<?php include __DIR__ . '/softphone.php'; ?>
<?php include __DIR__ . '/sticky-notes.php'; ?>
<?php include __DIR__ . '/time-tracker.php'; ?>
<?php include __DIR__ . '/share-modal.php'; ?>

<!-- Global Application Script -->
<script src="assets/js/modal-system.js"></script>
<script src="assets/js/app.js"></script>
<script src="assets/js/table-columns.js"></script>
<script src="assets/js/softphone.js"></script>
<script src="assets/js/communication.js"></script>
<script src="assets/js/sticky-notes.js"></script>
<script src="assets/js/time-tracker.js"></script>
<script src="assets/js/share-modal.js"></script>
<script src="assets/js/notifications.js"></script>
<script src="assets/js/quick-add.js"></script>
<script src="assets/js/global-search.js"></script>

<?php if (isset($page_script) && !empty($page_script)): ?>
    <script src="assets/js/<?php echo htmlspecialchars($page_script); ?>?v=<?= filemtime(__DIR__ . '/../assets/js/' . $page_script) ?>"></script>
<?php endif; ?>

</body>
</html>
