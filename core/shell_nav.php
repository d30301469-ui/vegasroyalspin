<?php

/**
 * Shell navigation — header/footer sabit kalır, yalnızca #shellPageHost içeriği değişir.
 */
function shell_nav_requested(): bool
{
    return isset($_SERVER['HTTP_X_SHELL_NAV']) && (string) $_SERVER['HTTP_X_SHELL_NAV'] === '1';
}

function shell_nav_fragment_mode(): bool
{
    return shell_nav_requested();
}
