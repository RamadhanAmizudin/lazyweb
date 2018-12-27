<?php
require_once './init.php';

session_destroy();

redirect('index.php');