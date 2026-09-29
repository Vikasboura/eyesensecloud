<?php
/**
 * Root Redirector
 * Automatically redirects requests from the root workspace directory to the hostel-admin folder.
 */
header('Location: hostel-admin/index.php');
exit;
