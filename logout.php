<?php
session_start(); // 啟動 session

// 清除所有 session 變數並銷毀 session
session_unset();
session_destroy();

// 跳轉到登入頁面
header("Location: login.php");
exit();
?>