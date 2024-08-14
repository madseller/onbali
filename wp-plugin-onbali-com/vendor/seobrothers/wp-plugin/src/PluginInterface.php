<?php

namespace Seobrothers\WP\Plugin;

interface PluginInterface
{
    function activate(): void;
	function deactivate(): void;
	
	static function uninstall(): void;
}