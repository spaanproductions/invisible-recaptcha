<?php

namespace SpaanProductions\InvisibleReCaptcha\Tests;

use SpaanProductions\InvisibleReCaptcha\InvisibleReCaptchaServiceProvider;

class TestCase extends \Orchestra\Testbench\TestCase
{
	protected function getPackageProviders($app)
	{
		return [
			InvisibleReCaptchaServiceProvider::class,
		];
	}
}
