<?php
use OpenApi\Attributes as OAT;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * This class contains all endpoints for managing categories
 */
class ApiCategory
{
    /**
     * This function lists all categories
     * @param $request The request.
     * @param $response The response.
     * @param $args The URL arguments.
     * @returns The response with a list of all categories.
     */
    #[OAT\Get(
        path: '/api/v1/categories',
        summary: 'Listet alle Kategorien auf.',
        tags: ['category'],
        security: [['cookieAuth' => []]],
        responses: [
            new OAT\Response(response: 401, description: 'Nicht authentifiziert (Token fehlt oder ist ungültig).'),
            new OAT\Response(response: 200, description: 'Liste aller kategorien.')
        ]
    )]
    public static function getAll(Request $request, Response $response, array $args)
    {
        $database = connect_database();
        $result = $database->query("SELECT id_category, active, name FROM category ORDER BY id_category");

        $categories = [ ];
        while ($row = $result->fetch_assoc()) {
            $categories[] = self::formatCategory($row);
        }

        return json_response($response, 200, $categories);
    }

    /**
     * This function returns a single category
     * @param $request The request
     * @param $response The response
     * @param $args The URL arguments
     * @returns The response with the category, or an error
     */
    #[OAT\Get(
        path: '/api/v1/category/{category_id}',
        summary: 'Gibt eine Kategorie zurück.',
        tags: ['category'],
        security: [['cookieAuth' => []]],
        parameters: [
            new OAT\Parameter(
                name: 'category_id',
                in: 'path',
                required: true,
                description: 'ID der Kategorie',
                schema: new OAT\Schema(type: 'integer', example: 1)
            )
        ],
        responses: [
            new OAT\Response(response: 401, description: 'Nicht authentifiziert (Token fehlt oder ist ungültig).'),
            new OAT\Response(response: 200, description: 'Die Kategorie.'),
            new OAT\Response(response: 400, description: 'Die id ist keine positive ganze Zahl.'),
            new OAT\Response(response: 404, description: 'Kategorie nicht gefunden.')
        ]
    )]
    public static function get(Request $request, Response $response, array $args)
    {
        $categoryId = parse_id($args["category_id"]);
        if ($categoryId === null) {
            return error_response($response, 400, "category_id must be a positive integer");
        }

        $category = self::findCategory(connect_database(), $categoryId);
        if ($category === null) {
            return error_response($response, 404, "Category not found");
        }

        return json_response($response, 200, $category);
    }

    /**
     * This function creates a new category
     * @param $request The request
     * @param $response The response
     * @param $args The URL arguments
     * @returns The response with the new category
     */
    #[OAT\Post(
        path: '/api/v1/category',
        summary: 'Erstellt eine neue Kategorie.',
        tags: ['category'],
        security: [['cookieAuth' => []]],
        requestBody: new OAT\RequestBody(
            required: true,
            content: new OAT\JsonContent(
                required: ['active', 'name'],
                properties: [
                    new OAT\Property(property: 'active', type: 'integer', enum: [0, 1], example: 1),
                    new OAT\Property(property: 'name', type: 'string', example: 'Firmen-Logos')
                ]
            )
        ),
        responses: [
            new OAT\Response(response: 401, description: 'Nicht authentifiziert (Token fehlt oder ist ungültig).'),
            new OAT\Response(response: 201, description: 'Kategorie erstellt, die neue ID steht in der Antwort.'),
            new OAT\Response(response: 400, description: 'Ungültige Daten.')
        ]
    )]
    public static function create(Request $request, Response $response, array $args)
    {
        $data = json_decode((string) $request->getBody(), true);
        if (!is_array($data)) {
            return error_response($response, 400, "The request body is not a valid JSON object");
        }

        $errors = self::validateCategory($data, false);
        if ($errors) {
            return error_response($response, 400, implode("; ", $errors));
        }

        $database = connect_database();
        $statement = $database->prepare("INSERT INTO category (active, name) VALUES (?, ?)");
        $statement->bind_param("is", $data["active"], $data["name"]);
        $statement->execute();

        // the database assigned the ID
        $categoryId = $database->insert_id;
        return json_response($response, 201, self::findCategory($database, $categoryId))
            ->withHeader("Location", "/api/v1/category/" . $categoryId);
    }

    /**
     * This function changes single properties of a category
     * @param $request The request
     * @param $response The response
     * @param $args The URL arguments
     * @returns The response without body 204 or an error
     */
    #[OAT\Patch(
        path: '/api/v1/category/{category_id}',
        summary: 'Ändert einzelne Eigenschaften einer Kategorie.',
        tags: ['category'],
        security: [['cookieAuth' => []]],
        parameters: [
            new OAT\Parameter(
                name: 'category_id',
                in: 'path',
                required: true,
                description: 'ID der Kategorie',
                schema: new OAT\Schema(type: 'integer', example: 1)
            )
        ],
        requestBody: new OAT\RequestBody(
            required: true,
            description: 'Mindestens eine Eigenschaft, nicht gesendete bleiben unverändert.',
            content: new OAT\JsonContent(
                properties: [
                    new OAT\Property(property: 'active', type: 'integer', enum: [0, 1], example: 0),
                    new OAT\Property(property: 'name', type: 'string', example: 'Tolle Firmen-Logos')
                ]
            )
        ),
        responses: [
            new OAT\Response(response: 401, description: 'Nicht authentifiziert (Token fehlt oder ist ungültig).'),
            new OAT\Response(response: 204, description: 'Kategorie geändert, keine Antwortdaten.'),
            new OAT\Response(response: 400, description: 'Ungültige ID oder ungültige Daten.'),
            new OAT\Response(response: 404, description: 'Kategorie nicht gefunden.')
        ]
    )]
    public static function update(Request $request, Response $response, array $args)
    {
        $categoryId = parse_id($args["category_id"]);
        if ($categoryId === null) {
            return error_response($response, 400, "category_id must be a positive integer");
        }

        $database = connect_database();
        $category = self::findCategory($database, $categoryId);
        if ($category === null) {
            return error_response($response, 404, "Category not found");
        }

        $data = json_decode((string) $request->getBody(), true);
        if (!is_array($data)) {
            return error_response($response, 400, "The request body is not a valid JSON object");
        }

        $errors = self::validateCategory($data, true);
        if ($errors) {
            return error_response($response, 400, implode("; ", $errors));
        }

        $active = $data["active"] ?? $category["active"];
        $name = $data["name"] ?? $category["name"];

        $statement = $database->prepare("UPDATE category SET active = ?, name = ? WHERE id_category = ?");
        $statement->bind_param("isi", $active, $name, $categoryId);
        $statement->execute();

        return $response->withStatus(204);
    }

    /**
     * This function deletes a category
     * @param $request The request
     * @param $response The response
     * @param $args The URL arguments
     * @returns The response without body 204 or an error
     */
    #[OAT\Delete(
        path: '/api/v1/category/{category_id}',
        summary: 'Löscht eine Kategorie. Ihre Produkte bleiben bestehen und sind danach nicht mehr gelistet.',
        tags: ['category'],
        security: [['cookieAuth' => []]],
        parameters: [
            new OAT\Parameter(
                name: 'category_id',
                in: 'path',
                required: true,
                description: 'ID der Kategorie',
                schema: new OAT\Schema(type: 'integer', example: 1)
            )
        ],
        responses: [
            new OAT\Response(response: 401, description: 'Nicht authentifiziert (Token fehlt oder ist ungültig).'),
            new OAT\Response(response: 204, description: 'Kategorie gelöscht.'),
            new OAT\Response(response: 400, description: 'Die ID ist keine positive ganze Zahl.'),
            new OAT\Response(response: 404, description: 'Kategorie nicht gefunden.')
        ]
    )]
    public static function delete(Request $request, Response $response, array $args)
    {
        $categoryId = parse_id($args["category_id"]);
        if ($categoryId === null) {
            return error_response($response, 400, "category_id must be a positive integer");
        }

        $database = connect_database();
        if (self::findCategory($database, $categoryId) === null) {
            return error_response($response, 404, "Category not found");
        }

        $statement = $database->prepare("DELETE FROM category WHERE id_category = ?");
        $statement->bind_param("i", $categoryId);
        $statement->execute();

        return $response->withStatus(204);
    }

    /**
     * This function searches a category by id
     * @param $database The open database connection
     * @param $categoryId The ID of the category
     * @returns The category as array
     */
    private static function findCategory(mysqli $database, int $categoryId): ?array
    {
        $statement = $database->prepare("SELECT id_category, active, name FROM category WHERE id_category = ?");
        $statement->bind_param("i", $categoryId);
        $statement->execute();
        $row = $statement->get_result()->fetch_assoc();
        return $row ? self::formatCategory($row) : null;
    }

    /**
     * This function converts a database in json
     * MySQL returns everything as text
     * @param $row The row from the category table
     * @returns The category with correct types
     */
    private static function formatCategory(array $row): array
    {
        return [
            "id_category" => (int) $row["id_category"],
            "active" => (int) $row["active"],
            "name" => $row["name"],
        ];
    }

    /**
     * This function checks the data of a category
     * @param $data The data from the request body
     * @param $isPartial
     * @returns A list of error messages
     */
    private static function validateCategory(array $data, bool $isPartial): array
    {
        if ($isPartial && !array_key_exists("active", $data) && !array_key_exists("name", $data)) {
            return ["active or name must be provided"];
        }

        $errors = [ ];
        if (!$isPartial || array_key_exists("active", $data)) {
            if (!isset($data["active"]) || !is_int($data["active"]) || !in_array($data["active"], [0, 1], true)) {
                $errors[] = "active must be 0 or 1";
            }
        }
        if (!$isPartial || array_key_exists("name", $data)) {
            if (!isset($data["name"]) || !is_string($data["name"]) || trim($data["name"]) === "") {
                $errors[] = "name must be a non-empty text";
            } elseif (mb_strlen($data["name"]) > 100) {
                $errors[] = "name must not be longer than 100 characters";
            }
        }
        return $errors;
    }
}
