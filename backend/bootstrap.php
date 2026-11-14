<?php
declare(strict_types=1);

use App\Core\Application;
use Dotenv\Dotenv;

require __DIR__ . '/environment.php';
return new Application(require __DIR__ . '/routes/api.php');
