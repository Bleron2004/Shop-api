<?php
use OpenApi\Attributes as OAT;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * This function opens the connection to the shop database
 * @returns The open mysqli connection
 */
function connect_database(): mysqli
{
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    $database = new mysqli("localhost", "root", "", "shop_api");
    $database->set_charset("utf8mb4");

    return $database;
}

/**
 * This function writes data as JSON in response
 * @param $response The response
 * @param $status The HTTP status code of the response
 * @param $data The data that is converted to json
 * @returns The response with body
 */
function json_response(Response $response, int $status, $data): Response
{
    $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE));
    return $response->withStatus($status)->withHeader("Content-Type", "application/json");
}

/**
 * This function answers with  error  
 * @param $response The response
 * @param $status The HTTP status code of the response
 * @param $message The error message
 * @returns The response with the error message
 */
function error_response(Response $response, int $status, string $message): Response
{
    return json_response($response, $status, ["error" => $message]);
}

/**
 * This function converts an id from the url into a number
 * @param $value The idd as text.
 * @returns The id as number
 */
function parse_id(string $value): ?int
{
    if (!ctype_digit($value) || (int) $value < 1 || (int) $value > 2147483647) {
        return null;
    }
    return (int) $value;
}

#[OAT\Info(
    title: 'Shop-API',
    version: '1.0.0',
    description: 'REST-API zum Verwalten von Produkten und Kategorien.'
)]
#[OAT\Server(url: 'http://localhost', description: 'Lokaler XAMPP-Server')]
#[OAT\SecurityScheme(securityScheme: 'cookieAuth', type: 'apiKey', in: 'cookie', name: 'access_token')]
/**
 * This class contains the entry point
 */
class ApiMain
{
    /**
     * This function answers requests
     * @param $request The request
     * @param $response The response
     * @param $args The url arguments
     * @returns The response with a greeting
     */
    #[OAT\Get(
        path: '/api/v1/',
        summary: 'Einstiegspunkt der API.',
        tags: ['main'],
        security: [['cookieAuth' => []]],
        responses: [
            new OAT\Response(response: 401, description: 'Nicht authentifiziert (Token fehlt oder ist ungültig).'),
            new OAT\Response(response: 200, description: 'Begrüssung.')
        ]
    )]
    public static function index(Request $request, Response $response, array $args)
    {
        $response->getBody()->write("Hello, world!");
        return $response;
    }
}
