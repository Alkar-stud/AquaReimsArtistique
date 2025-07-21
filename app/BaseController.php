<?php
namespace app;

abstract class BaseController
{
    protected function render($title = '', $config = [])
    {
        require __DIR__ . '/../config/env.php';
        $class = (new \ReflectionClass($this))->getShortName();
        $view = strtolower(str_replace('Controller', '', $class)) . '.html.php';
        $page = __DIR__ . '/views/' . $view;
        require __DIR__ . '/views/base.html.php';
    }
}