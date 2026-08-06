<?php
require_once __DIR__ . '/../../auth.php';

json_ok(current_user());
