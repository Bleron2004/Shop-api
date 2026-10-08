<?php
use OpenApi\Attributes as OAT;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * This class contains all endpoints for managing products
 */
class ApiProduct
{
    /**
     * This function lists all products
     * @param $request The request
     * @param $response The response
     * @param $args The URL arguments
     * @returns The response with a list of all products
     */
    #[OAT\Get(
        path: '/api/v1/products',
        summary: 'Listet alle Produkte auf.',
        tags: ['product'],
        security: [['cookieAuth' => []]],
        responses: [
            new OAT\Response(response: 401, description: 'Nicht authentifiziert (Token fehlt oder ist ungültig).'),
            new OAT\Response(response: 200, description: 'Liste aller Produkte.')
        ]
    )]
    public static function getAll(Request $request, Response $response, array $args)
    {
        $database = connect_database();
        $result = $database->query("SELECT * FROM product ORDER BY id_product");

        $products = [ ];
        while ($row = $result->fetch_assoc()) {
            $products[] = self::formatProduct($row);
        }

        return json_response($response, 200, $products);
    }

    /**
     * This function returns a single product
     * @param $request The request
     * @param $response The response
     * @param $args The URL arguments
     * @returns The response with the product, or an error
     */
    #[OAT\Get(
        path: '/api/v1/product/{product_id}',
        summary: 'Gibt ein Produkt zurück.',
        tags: ['product'],
        security: [['cookieAuth' => []]],
        parameters: [
            new OAT\Parameter(
                name: 'product_id',
                in: 'path',
                required: true,
                description: 'ID des Produkts',
                schema: new OAT\Schema(type: 'integer', example: 12345678)
            )
        ],
        responses: [
            new OAT\Response(response: 401, description: 'Nicht authentifiziert (Token fehlt oder ist ungültig).'),
            new OAT\Response(response: 200, description: 'Das Produkt.'),
            new OAT\Response(response: 400, description: 'Die ID ist keine positive ganze Zahl.'),
            new OAT\Response(response: 404, description: 'Produkt nicht gefunden.')
        ]
    )]
    public static function get(Request $request, Response $response, array $args)
    {
        $productId = parse_id($args["product_id"]);
        if ($productId === null) {
            return error_response($response, 400, "product_id must be a positive integer");
        }

        $product = self::findProduct(connect_database(), $productId);
        if ($product === null) {
            return error_response($response, 404, "Product not found");
        }

        return json_response($response, 200, $product);
    }

    /**
     * This function creates a product
     * @param $request The request
     * @param $response The response
     * @param $args The URL arguments
     * @returns The response 201 if created or 204 if replaced
     */
    #[OAT\Put(
        path: '/api/v1/product/{product_id}',
        summary: 'Erstellt ein Produkt mit dieser ID oder ersetzt das bestehende.',
        tags: ['product'],
        security: [['cookieAuth' => []]],
        parameters: [
            new OAT\Parameter(
                name: 'product_id',
                in: 'path',
                required: true,
                description: 'ID des Produkts (bestimmt der Client)',
                schema: new OAT\Schema(type: 'integer', example: 12345678)
            )
        ],
        requestBody: new OAT\RequestBody(
            required: true,
            description: 'Alle Eigenschaften des Produkts.',
            content: new OAT\JsonContent(
                required: ['active', 'id_category', 'name', 'image', 'description', 'price', 'stock'],
                properties: [
                    new OAT\Property(property: 'active', type: 'integer', enum: [0, 1], example: 1),
                    new OAT\Property(property: 'id_category', type: 'integer', nullable: true, example: 1, description: 'null = nicht gelistet'),
                    new OAT\Property(property: 'name', type: 'string', example: 'CsBe-Logo'),
                    new OAT\Property(property: 'image', type: 'string', example: 'https://www.csbe.ch/logo.svg'),
                    new OAT\Property(property: 'description', type: 'string', example: 'Kaufen Sie jetzt das tolle Logo der CsBe!'),
                    new OAT\Property(property: 'price', type: 'number', format: 'float', example: 39999.95),
                    new OAT\Property(property: 'stock', type: 'integer', example: 3)
                ]
            )
        ),
        responses: [
            new OAT\Response(response: 401, description: 'Nicht authentifiziert (Token fehlt oder ist ungültig).'),
            new OAT\Response(response: 201, description: 'Produkt neu erstellt, keine Antwortdaten.'),
            new OAT\Response(response: 204, description: 'Bestehendes Produkt ersetzt, keine Antwortdaten.'),
            new OAT\Response(response: 400, description: 'Ungültige ID oder Daten (auch wenn die Kategorie nicht existiert).')
        ]
    )]
    public static function put(Request $request, Response $response, array $args)
    {
        $productId = parse_id($args["product_id"]);
        if ($productId === null) {
            return error_response($response, 400, "product_id must be a positive integer");
        }

        $data = json_decode((string) $request->getBody(), true);
        if (!is_array($data)) {
            return error_response($response, 400, "The request body is not a valid JSON object");
        }

        $errors = self::validateProduct($data);
        if ($errors) {
            return error_response($response, 400, implode("; ", $errors));
        }

        $database = connect_database();

        if ($data["id_category"] !== null && !self::hasCategory($database, $data["id_category"])) {
            return error_response($response, 400, "id_category: this category does not exist");
        }

        if (self::findProduct($database, $productId) === null) {
            $statement = $database->prepare(
                "INSERT INTO product (id_product, id_category, active, name, image, description, price, stock)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $statement->bind_param(
                "iiisssdi",
                $productId, $data["id_category"], $data["active"], $data["name"],
                $data["image"], $data["description"], $data["price"], $data["stock"]
            );
            $status = 201;
        } else {
            $statement = $database->prepare(
                "UPDATE product SET id_category = ?, active = ?, name = ?, image = ?,
                 description = ?, price = ?, stock = ? WHERE id_product = ?"
            );
            $statement->bind_param(
                "iisssdii",
                $data["id_category"], $data["active"], $data["name"], $data["image"],
                $data["description"], $data["price"], $data["stock"], $productId
            );
            $status = 204;
        }
        $statement->execute();

        return $response->withStatus($status);
    }

    /**
     * This function deletes a product
     * @param $request The request
     * @param $response The response
     * @param $args The URL arguments
     * @returns The response 204 or error
     */
    #[OAT\Delete(
        path: '/api/v1/product/{product_id}',
        summary: 'Löscht ein Produkt.',
        tags: ['product'],
        security: [['cookieAuth' => []]],
        parameters: [
            new OAT\Parameter(
                name: 'product_id',
                in: 'path',
                required: true,
                description: 'ID des Produkts',
                schema: new OAT\Schema(type: 'integer', example: 12345678)
            )
        ],
        responses: [
            new OAT\Response(response: 401, description: 'Nicht authentifiziert (Token fehlt oder ist ungültig).'),
            new OAT\Response(response: 204, description: 'Produkt gelöscht.'),
            new OAT\Response(response: 400, description: 'Die ID ist keine positive ganze Zahl.'),
            new OAT\Response(response: 404, description: 'Produkt nicht gefunden.')
        ]
    )]
    public static function delete(Request $request, Response $response, array $args)
    {
        $productId = parse_id($args["product_id"]);
        if ($productId === null) {
            return error_response($response, 400, "product_id must be a positive integer");
        }

        $database = connect_database();
        if (self::findProduct($database, $productId) === null) {
            return error_response($response, 404, "Product not found");
        }

        $statement = $database->prepare("DELETE FROM product WHERE id_product = ?");
        $statement->bind_param("i", $productId);
        $statement->execute();

        return $response->withStatus(204);
    }

    /**
     * This function searches a product by its id
     * @param $database The open database connection
     * @param $productId The ID of the product
     * @returns The product as array
     */
    private static function findProduct(mysqli $database, int $productId): ?array
    {
        $statement = $database->prepare("SELECT * FROM product WHERE id_product = ?");
        $statement->bind_param("i", $productId);
        $statement->execute();
        $row = $statement->get_result()->fetch_assoc();
        return $row ? self::formatProduct($row) : null;
    }

    /**
     * This function checks if a category exists
     * @param $database The open database connection
     * @param $categoryId The id of the category
     * @returns True if the category exists
     */
    private static function hasCategory(mysqli $database, int $categoryId): bool
    {
        $statement = $database->prepare("SELECT 1 FROM category WHERE id_category = ?");
        $statement->bind_param("i", $categoryId);
        $statement->execute();
        return $statement->get_result()->num_rows > 0;
    }

    /**
     * This function checks all properties of product
     * @param $data The data from the request body
     * @returns A list of error messages empty if  data is valid
     */
    private static function validateProduct(array $data): array
    {
        $errors = [ ];

        if (!isset($data["active"]) || !is_int($data["active"]) || !in_array($data["active"], [0, 1], true)) {
            $errors[] = "active must be 0 or 1";
        }

        // id_category has to be present but may be null
        if (!array_key_exists("id_category", $data)) {
            $errors[] = "id_category is missing (null = not listed)";
        } elseif ($data["id_category"] !== null && (!is_int($data["id_category"]) || $data["id_category"] < 1)) {
            $errors[] = "id_category must be a positive integer or null";
        }

        if (!isset($data["name"]) || !is_string($data["name"]) || trim($data["name"]) === "") {
            $errors[] = "name must be a non-empty text";
        } elseif (mb_strlen($data["name"]) > 100) {
            $errors[] = "The product name is too long";
        }

        if (!isset($data["image"]) || !is_string($data["image"]) || !filter_var($data["image"], FILTER_VALIDATE_URL)) {
            $errors[] = "image must be a valid URL";
        } elseif (mb_strlen($data["image"]) > 500) {
            $errors[] = "image must not be longer than 500 characters";
        }

        if (!isset($data["description"]) || !is_string($data["description"])) {
            $errors[] = "description must be a text";
        }

        if (!isset($data["price"]) || !(is_int($data["price"]) || is_float($data["price"]))
            || $data["price"] < 0 || $data["price"] > 99999999.99
            || abs(round($data["price"], 2) - $data["price"]) > 0.0000001) {
            $errors[] = "price must be a number from 0 to 99999999.99 with at most 2 decimal places";
        }

        if (!isset($data["stock"]) || !is_int($data["stock"]) || $data["stock"] < 0) {
            $errors[] = "stock must be an integer from 0";
        }

        return $errors;
    }

    /**
     * This function converts a database into json
     * MySQL returns everything as text
     * @param $row The row from the product table
     * @returns The product with correct types
     */
    private static function formatProduct(array $row): array
    {
        return [
            "id_product" => (int) $row["id_product"],
            "active" => (int) $row["active"],
            "id_category" => $row["id_category"] === null ? null : (int) $row["id_category"],
            "name" => $row["name"],
            "image" => $row["image"],
            "description" => $row["description"],
            "price" => (float) $row["price"],
            "stock" => (int) $row["stock"],
        ];
    }
}
