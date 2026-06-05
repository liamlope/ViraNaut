<?php

function vira_is_start_param(string $text, string $param): bool
{
    $text = trim($text);
    return preg_match('#^/start\s+' . preg_quote($param, '#') . '$#i', $text) === 1;
}
