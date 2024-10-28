<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Client;

class UserStatusController extends Controller
{
    private function getManagementAccessToken()
    {
        $client = new Client();

        try {
            $response = $client->post(env('WSO2_TOKEN_URL'), [
                'form_params' => [
                    'grant_type' => 'client_credentials',
                    'client_id' => env('WSO2_CLIENT_ID'),
                    'client_secret' => env('WSO2_CLIENT_SECRET'),
                ],
                'verify' => false,
            ]);

            $data = json_decode($response->getBody(), true);
            return $data['access_token'];
        } catch (\Exception $e) {
            throw new \Exception('Error al obtener el token de acceso: ' . $e->getMessage());
        }
    }

    private function getAdminAccessToken() {
        $code = $this->getManagementAccessToken();
        $redirectUri = 'https://localhost:9443/scim2/Users/00ffff67-d2a9-4369-9e0d-340365a78c37';
        $client = new Client();

        $response = $client->request('POST', 'https://localhost:9443/oauth2/token', [
            'form_params' => [
                'grant_type' => 'authorization_code',
                'client_id' => env('WSO2_CLIENT_ID'),
                'client_secret' => env('WSO2_CLIENT_SECRET'),
                'code' => $code,
                'redirect_uri' => $redirectUri,
            ],
            'verify' => false,
        ]);

        $accessToken = json_decode($response->getBody(), true);
        $token = $accessToken['access_token'];
        return $token;
    }

    public function getIdUser($userName) {
        $accessToken = $this->getManagementAccessToken();
        $client = new \GuzzleHttp\Client();

        try {
            $response = $client->get('https://localhost:9443/wso2/scim/Users?filter=userName eq "' . $userName . '"', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                ],
                'verify' => false,
            ]);

            $responseBody = $response->getBody()->getContents();
            $userData = json_decode($responseBody, true);

            if ($userData['totalResults'] > 0) {
                $idUser = $userData['Resources'][0]['id'];
                return $idUser;
            }

        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();
                $statusText = $e->getResponse()->getReasonPhrase();
                $errorBody = $e->getResponse()->getBody()->getContents();

                return response()->json([
                    'error' => $statusText,
                    'code' => $statusCode,
                    'details' => json_decode($errorBody, true),
                ], $statusCode);
            } else {
                return response()->json(['error' => $e->getMessage()], 500);
            }
        } catch (\Exception $e) {
            return 'usuario inexistente';
        }
    }

    public function blockUser($userId) {

        $client = new Client();

        try {
            $response = $client->request('PATCH', 'https://localhost:9443/t/carbon.super/scim2/Users/'."$userId",[
                'headers' => [
                    'Authorization' => "Basic YWRtaW46YWRtaW4",
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'Operations' => [
                        [
                            'op' => 'replace',
                            'value' => [
                                'urn:ietf:params:scim:schemas:extension:enterprise:2.0:User' => [
                                    'accountLocked' => true
                                ],
                            ]
                        ]
                    ],
                    'schemas' => [
                        'urn:ietf:params:scim:api:messages:2.0:PatchOp'
                    ]

                 ],
                 'verify' => false,
            ]);

            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            return 'Error: ' . $e->getMessage();
        }
    }

    public function unblockUser($userId) {
        $client = new Client();

        try {
            $response = $client->request('PATCH', 'https://localhost:9443/t/carbon.super/scim2/Users/'."$userId",[
                'headers' => [
                    'Authorization' => "Basic YWRtaW46YWRtaW4",
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'Operations' => [
                        [
                            'op' => 'replace',
                            'value' => [
                                'urn:ietf:params:scim:schemas:extension:enterprise:2.0:User' => [
                                    'accountLocked' => false
                                ],
                            ]
                        ]
                    ],
                    'schemas' => [
                        'urn:ietf:params:scim:api:messages:2.0:PatchOp'
                    ]

                 ],
                 'verify' => false,
            ]);

            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            return 'Error: ' . $e->getMessage();
        }
    }

    public function massiveBlock(Request $request) {
        try {
            $usersCollection = $request->input('usuarios');
            $arrUsers = [];
            
            foreach ($usersCollection as $user) {
                $idUser = $this->getIdUser($user);

                if($idUser != "usuario inexistente") {
                    array_push($arrUsers, $idUser);
                }

                $bloqUsuario = $this->blockUser($idUser);

                if($bloqUsuario['urn:ietf:params:scim:schemas:extension:enterprise:2.0:User']['accountState'] != "LOCKED") {
                    return response()->json(['error' => 'Error al bloquear usuario: ' + $idUser], 500);
                }
            }
        } catch (\Throwable $e) {
            return 'Error: ' . $e->getMessage();
        }
    }
}
