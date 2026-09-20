<?php
require __DIR__ . '/../config.php';
logout_user();
redirect(url('admin/login.php'));
