<?php

namespace Seobrothers\WP\Plugin;

abstract class PluginAbstract
implements PluginInterface
{
    const HOOK_TYPES = [
		"action" => true,
		"filter" => true
	];

    const HOOK_FNS = [
		"add" => true,
		"remove" => true
	];

	public readonly string $dir;
	public readonly string $id;
	public readonly string $name;
	public readonly string $title;
	public readonly ?object $config;

	function __construct
	(
		public readonly string $file,
		array $config = []
	)
	{
		if ( ! is_file($this->file))
			throw new PluginException("File not found: {$this->file}");

		$this->config = static::arrayToObject($config);

		$this->dir = dirname($this->file);

		// Replace each '\\' with '-' and lowercase.
		$this->id = $this->config->id ?? strtolower(
			preg_replace(
				'#(?!^)([[:lower:]])([[:upper:]])#',
				'$1-$2',
				preg_replace('#^.+\\\#', '', static::class)
			)
		);

		$this->name = str_replace('-', '_', $this->id);

		// Replace each '\\' with ' '/ lowercase and ucfirst each word.
		$this->title = $this->config->title ?? preg_replace(
			'#(?!^)([[:lower:]])([[:upper:]])#',
			'$1 $2',
			preg_replace(
				'#(?!^)([[:upper:]][[:lower:]]+)#',
				' $0',
				$title = preg_replace('#^.+\\\#', '', static::class)
			)
				?? $title
		)
			?? $title;
	}

	function register(): void
	{
		\register_activation_hook($this->file,
			[$this, 'activate']);
        
        \register_deactivation_hook($this->file,
			[$this, 'deactivate']);
	}

    function activate(): void
    {
		if (method_exists(static::class, 'uninstall'))
		{
			\register_uninstall_hook($this->file,
				[static::class, 'uninstall']);
		}
    }

    function deactivate(): void
    {
        
    }

	static function uninstall(): void
	{

	}

	static function arrayToObject(array $array): object
	{
		return (object) array_reduce(
		    array_keys($array),
			fn ($object, $key) => $object
			    + [
    			    $key =>
						is_array(($array[$key]))
    			        && ! empty(($array[$key]))
    			        && ! array_is_list(($array[$key]))
            		        ? static::arrayToObject($array[$key])
            				: $array[$key]
				],
			[]
		);
	}
}