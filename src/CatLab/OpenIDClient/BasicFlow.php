<?php

namespace CatLab\OpenIDClient;

use CatLab\OpenIDClient\Exceptions\OpenIDConnectException;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Minimal OpenID Connect authorization-code flow.
 *
 * Replaces InoOicClient\Flow\Basic (dropped in 4.0). The call sequence and
 * wire behavior match 3.x: authorize redirect (with state), token POST
 * (client_secret_post), userinfo GET (Bearer).
 */
class BasicFlow
{
    const SESSION_STATE_KEY = 'catlab_oidc_state';

    /**
     * @var array
     */
    protected $config;

    /**
     * @var ClientInterface
     */
    protected $httpClient;

    /**
     * @param array $config The openid.client config array.
     * @param ClientInterface|null $httpClient
     */
    public function __construct(array $config, ?ClientInterface $httpClient = null)
    {
        $this->config = $config;
        $this->httpClient = $httpClient ?? new GuzzleClient();
    }

    /**
     * Build the authorization redirect URI and remember the state.
     * @param string|array $scope
     * @param string $responseType
     * @return string
     */
    public function getAuthorizationRequestUri($scope = 'openid', $responseType = 'code')
    {
        $state = bin2hex(random_bytes(16));
        $_SESSION[self::SESSION_STATE_KEY] = $state;

        $params = [
            'client_id' => $this->config['client_id'],
            'redirect_uri' => $this->config['redirect_uri'],
            'response_type' => is_array($responseType) ? implode(' ', $responseType) : $responseType,
            'scope' => is_array($scope) ? implode(' ', $scope) : $scope,
            'state' => $state
        ];

        return $this->config['authorization_endpoint'] . '?' . http_build_query($params);
    }

    /**
     * Validate the callback query and return the authorization code.
     * @param array|null $query Defaults to $_GET.
     * @return string
     * @throws OpenIDConnectException
     */
    public function getAuthorizationCode(?array $query = null)
    {
        $query = $query ?? $_GET;

        if (isset($query['error'])) {
            throw new OpenIDConnectException(
                'Authorization error: ' . $query['error']
                . (isset($query['error_description']) ? ' (' . $query['error_description'] . ')' : '')
            );
        }

        $expectedState = $_SESSION[self::SESSION_STATE_KEY] ?? null;
        unset($_SESSION[self::SESSION_STATE_KEY]);

        $state = $query['state'] ?? null;
        if (!is_string($expectedState) || !is_string($state) || !hash_equals($expectedState, $state)) {
            throw new OpenIDConnectException('State validation failed.');
        }

        if (empty($query['code'])) {
            throw new OpenIDConnectException('No code in response.');
        }

        return $query['code'];
    }

    /**
     * Exchange the authorization code for an access token.
     * @param string $authorizationCode
     * @return string
     * @throws OpenIDConnectException
     */
    public function getAccessToken($authorizationCode)
    {
        $body = $this->requestJson('POST', $this->config['token_endpoint'], [
            'form_params' => [
                'client_id' => $this->config['client_id'],
                'redirect_uri' => $this->config['redirect_uri'],
                'grant_type' => 'authorization_code',
                'code' => $authorizationCode,
                'client_secret' => $this->config['authentication_info']['params']['client_secret'] ?? null
            ]
        ]);

        if (isset($body['error'])) {
            throw new OpenIDConnectException('Token error: ' . $body['error']);
        }

        if (empty($body['access_token'])) {
            throw new OpenIDConnectException('No access_token in token response.');
        }

        return $body['access_token'];
    }

    /**
     * Fetch the userinfo claims.
     * @param string $accessToken
     * @return array
     * @throws OpenIDConnectException
     */
    public function getUserInfo($accessToken)
    {
        return $this->requestJson('GET', $this->config['user_info_endpoint'], [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken
            ]
        ]);
    }

    /**
     * @param string $method
     * @param string $url
     * @param array $options
     * @return array
     * @throws OpenIDConnectException
     */
    protected function requestJson($method, $url, array $options)
    {
        try {
            $response = $this->httpClient->request($method, $url, $options);
        } catch (GuzzleException $e) {
            throw new OpenIDConnectException(
                'OpenID Connect request failed: ' . $e->getMessage(), 0, $e
            );
        }

        $body = json_decode($response->getBody()->getContents(), true);
        if (!is_array($body)) {
            throw new OpenIDConnectException('OpenID Connect endpoint returned invalid JSON.');
        }

        return $body;
    }
}
