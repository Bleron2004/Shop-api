<?php
use Slim\Factory\AppFactory;

require __DIR__ . "/../vendor/autoload.php";

require "api/api-main.php";
require "api/api-category.php";
require "api/api-product.php";
require "api/api-auth.php";

$app = AppFactory::create();
$app->setBasePath("/api/v1");

// every endpoint except the login needs a valid access token
$app->add(new AuthMiddleware());

// errors like 404 and 405 are always answered as JSON
$errorMiddleware = $app->addErrorMiddleware(false, true, true);
$errorMiddleware->getDefaultErrorHandler()->forceContentType("application/json");

$app->get("/", [ApiMain::class, "index"]);
$app->post("/authenticate", [ApiAuth::class, "authenticate"]);
$app->get("/categories", [ApiCategory::class, "getAll"]);
$app->get("/category/{category_id}", [ApiCategory::class, "get"]);
$app->post("/category", [ApiCategory::class, "create"]);
$app->patch("/category/{category_id}", [ApiCategory::class, "update"]);
$app->delete("/category/{category_id}", [ApiCategory::class, "delete"]);

$app->get("/products", [ApiProduct::class, "getAll"]);
$app->get("/product/{product_id}", [ApiProduct::class, "get"]);
$app->put("/product/{product_id}", [ApiProduct::class, "put"]);
$app->delete("/product/{product_id}", [ApiProduct::class, "delete"]);

$app->run();
