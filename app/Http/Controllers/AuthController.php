<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required',
            'password' => 'required',
        ]);

        $client = new Client();

        try {
            $url = 'https://localhost:9443/api/identity/auth/v1.1/authenticate';
            $body = [
                'username' => $request->username,
                'password' => $request->password,
            ];

            $response = $client->post($url, [
                'headers' => [
                    'accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'json' => $body,
                'verify' => false,
            ]);
            return response()->json(json_decode($response->getBody(), true), $response->getStatusCode());
        } catch (\Exception $e) {
            $responseBody = $e->getResponse()->getBody()->getContents();
            $decodedResponse = json_decode($responseBody, true);

            if(json_last_error() === JSON_ERROR_NONE && $decodedResponse['code'] == "17003:AdminInitiated"){
                return response()->json([
                    'error' => "Blocked User",
                    'message' => "The user is blocked, please contact support.",
                ], $e->getCode() ?: 400);
            }

            if (json_last_error() === JSON_ERROR_NONE && isset($decodedResponse['code'])) {
                return response()->json([
                    'error' => $decodedResponse['code'],
                    'message' => $decodedResponse['description'] ?? 'Error desconocido',
                ], $e->getCode() ?: 400);
            }

            return response()->json([
                'error' => 'Error al autenticar',
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 500);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al autenticar',
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 500);
        }
    }

    public function createUser(Request $request)
    {
        $request->validate([
            'username' => 'required',
            'password' => 'required',
            'email' => 'required|email',
        ]);

        $accessToken = $this->getManagementAccessToken();
        $client = new Client();

        try {
            $response = $client->post('https://localhost:9443/wso2/scim/Users', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                ],
                'verify' => false,
                'json' => [
                    'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:User'],
                    'userName' => $request->username,
                    'name' => [
                        'givenName' => $request->username,
                        'familyName' => 'Apellido',
                    ],
                    'emails' => $request->emails,
                    'active' => true,
                    'password' => $request->password,
                ],
            ]);

            $data = json_decode($response->getBody(), true);
            return response()->json($data, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

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

    public function resetPassword(Request $request)
    {
        try {
            if(empty($request->email)){
                $validateUser = $this->validateUserExists($request->input('userName'));
                $idUser = $validateUser->original['Resources']['0']['id'];
                $email = self::getEmailByUser($idUser);
                $emailValidate = $email->original['email'];
            }else{
                $emailValidate = $request->email;
            }
            

            $client = new Client();
            $response = $client->request('GET', 'https://localhost:9443/scim2/Users?filter=emails+eq+'."$emailValidate", [
                'headers' => [
                    'Authorization' => 'Basic YWRtaW46YWRtaW4='
                ],
                'verify' => false,
            ]);
            $dataUser = json_decode($response->getBody(), true);
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()], 400);

        }

        $url = 'https://localhost:9443/wso2/scim/Users/'.$dataUser['Resources'][0]['id'];

        $body = [
            'password' => $request->password,
            'userName' => $request->userName,
        ];

        try {
            $accessToken = $this->getManagementAccessToken();
            $response = $client->patch($url, [
                'headers' => [
                    'accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
                'json' => $body,
                'verify' => false,
            ]);

            return response()->json([
                'data' => json_decode($response->getBody(), true), $response->getStatusCode(),
                'status' => true
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Error al enviar el enlace de recuperación',
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 500);
        }
    }

    public function searchUserById(Request $request)
    {
        $request->validate([
            'idUser' => 'required'
        ]);

        $accessToken = $this->getManagementAccessToken();
        $client = new Client();

        try {
            $response = $client->get('https://localhost:9443/wso2/scim/Users/'.$request->idUser, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                ],
                'verify' => false,
            ]);

            $responseBody = $response->getBody()->getContents();
            $userData = json_decode($responseBody, true);

            return response()->json($userData, 201);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    private function getEmailByUser($id)
    {
        try {
            $client = new Client();
            $response = $client->request('GET', 'https://localhost:9443/scim2/Users/'."$id", [
                'headers' => [
                    'Authorization' => 'Basic YWRtaW46YWRtaW4='
                ],
                'verify' => false,
            ]);
            $dataUser = json_decode($response->getBody(), true);
            if(!empty($dataUser['emails'][0])){
                return response()->json([
                    'status' => true,
                    'email' => $dataUser['emails'][0]
                ], 200);
            }else{
                return response()->json([
                    'status' => false,
                    'msg' => 'Usuario no encontrado, favor contactar a soporte'
                ],404);
            }

        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'msg' => $th->getMessage()
            ],500);
        }
    }

    public function validateUserExists($user) 
    {
        $accessToken = $this->getManagementAccessToken();
        $client = new \GuzzleHttp\Client();

        try {
            $response = $client->get('https://localhost:9443/wso2/scim/Users?filter=userName eq "' . $user . '"', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                ],
                'verify' => false,
            ]);
            
            $responseBody = $response->getBody()->getContents();
            $userData = json_decode($responseBody, true);

            if ($userData['totalResults'] > 0) {
                return response()->json($userData, 200);
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
            return response()->json(['error' => 'Usuario Inexistente'], 404);
        }
    }
}
