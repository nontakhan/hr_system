<?php
// Authorization still reads the request's $_SESSION snapshot after releasing the file lock.
function hrSessionRelease(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
}
