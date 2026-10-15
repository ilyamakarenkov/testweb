<?php

require_once dirname(__FILE__) . '/../includes/auth.php';
logout();
flash('success', 'Вы вышли из системы.');
redirect(url('index.php'));