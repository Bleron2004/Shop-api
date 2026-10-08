<?php
use OpenApi\Attributes as OAT;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use ReallySimpleJWT\Token;

/**
 * This class contains the login endpoint, it creates the access token
 */
class ApiAuth
{
    // the secret needs at least 12 characters
    const SECRET = "Shop!Api2026#Schluessel";
    const LIFETIME_IN_SECONDS = 3600;

    // username => password
    const USERS = ["admin" => "sec!ReT423*&"];

    /**
     * This function checks username and password and creates an access token
     * The token is returned in the body and as cookie
     * @param $request The request
     * @param $response The response
     * @param $args The URL arguments
     * @returns The response with the token, or an error
     */
    #[OAT\Post(
        path: '/api/v1/authenticate',
        summary: 'Meldet einen Benutzer an und gibt ein JWT zurück.',
        tags: ['authentication'],
        requestBody: new OAT\RequestBody(
            required: true,
            content: new OAT\JsonContent(
                required: ['username', 'password'],
                properties: [
                    new OAT\Property(property: 'username', type: 'string', example: 'admin'),
                    new OAT\Property(property: 'password', type: 'string', example: 'geheim')
                ]
            )
        ),
        responses: [
            new OAT\Response(response: 200, description: 'Login erfolgreich.'),
            new OAT\Response(response: 400, description: 'username oder password fehlt.'),
            new OAT\Response(response: 401, description: 'Benutzername oder Passwort falsch.')
        ]
    )]
    public static function authenticate(Request $request, Response $response, array $args)
    {
        $data = json_decode((string) $request->getBody(), true);
        if (!is_array($data) || !isset($data["username"], $data["password"])
            || !is_string($data["username"]) || !is_string($data["password"])) {
            return error_response($response, 400, "username and password are required");
        }

        $knownPassword = self::USERS[$data["username"]] ?? null;
        if ($knownPassword === null || !hash_equals($knownPassword, $data["password"])) {
            return error_response($response, 401, "Wrong username or password");
        }

        $token = Token::create($data["username"], self::SECRET, time() + self::LIFETIME_IN_SECONDS, "localhost");

        $cookie = "access_token=" . $token . "; Path=/; HttpOnly; SameSite=Lax; Max-Age=" . self::LIFETIME_IN_SECONDS;
        return json_response($response, 200, ["token" => $token])->withAddedHeader("Set-Cookie", $cookie);
    }
}

/**
 * This class checks the access token before every endpoint
 */
class AuthMiddleware implements MiddlewareInterface
{
    /**
     * This function lets the request pass if the access token cookie is valid
     * @param $request The request
     * @param $handler The next handler, it calls the endpoint
     * @returns The response of the endpoint
     */
    public function process(Request $request, RequestHandler $handler): Response
    {
        if ($request->getUri()->getPath() === "/api/v1/authenticate") {
            return $handler->handle($request);
        }

        $token = $request->getCookieParams()["access_token"] ?? "";
        try {
            $isValid = Token::validate($token, ApiAuth::SECRET);
        } catch (Throwable $exception) {
            $isValid = false;
        }

        if (!$isValid) {
            return error_response(new Slim\Psr7\Response(), 401, "Missing or invalid access token");
        }
        return $handler->handle($request);
    }
}
