<?php

require __DIR__ . '/../lib/db.php';
session_destroy();
redirect('admin/login.php');
