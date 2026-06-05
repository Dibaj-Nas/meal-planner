$router = new ()

// Page login
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);

// Page ingredient avec paramètre
$router->get('/ingredient/:id', [MealController::class, 'show']);
$router->post('/ingredient/:id/delete', [MealController::class, 'destroy']);

$router->dispatch();