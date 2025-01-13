<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Client;
use Maatwebsite\Excel\Facades\Excel;

class UserStatusController extends Controller
{
    private function getManagementAccessToken()
    {
        $client = new Client();

        try {
            $url = env('KEYCLOAK_BASE_URL').'/realms/'.env('KEYCLOAK_REALM').'/protocol/openid-connect/token';

            $body = [
                'grant_type' => 'client_credentials',
                'client_id' => env('KEYCLOAK_CLIENT_ID'),
                'client_secret' => env('KEYCLOAK_CLIENT_SECRET'),
            ];

            $response = $client->post($url, [
                'headers' => [
                    'accept' => 'application/json',
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'form_params' => $body,
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

    public function getInfoUser($user)
    {
        $accessToken = $this->getManagementAccessToken();
        $client = new \GuzzleHttp\Client();

        try {
            $response = $client->get(env('KEYCLOAK_BASE_URL'). "/". env('KEYCLOAK_REALM').'/realms/'.env('KEYCLOAK_REALM').'/users', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                ],
                'query' => [
                    'username' => $user
                ],
                'verify' => false,
            ]);

            $userData = json_decode($response->getBody(), true);

            if($userData) {
                return $userData;
            } else {
                return response()->json(['error' => 'No se encuentra el user ID'], 404);
            }

        } catch (RequestException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function blockUser($userId) {

        $client = new Client();

        try {
            $token = $this->getManagementAccessToken();

            $body = [
                'enabled' => false
            ];

            $client->put(env('KEYCLOAK_BASE_URL'). "/". env('KEYCLOAK_REALM').'/realms/'.env('KEYCLOAK_REALM').'/users/'.$userId, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                ],
                'json' => $body,
                'verify' => false,
            ]);
        } catch (\Exception $e) {
            return 'Error: ' . $e->getMessage();
        }
    }

    public function unblockUser($userId) {
        $client = new Client();

        try {
            $token = $this->getManagementAccessToken();

            $body = [
                'enabled' => true
            ];

            $client->put(env('KEYCLOAK_BASE_URL'). "/". env('KEYCLOAK_REALM').'/realms/'.env('KEYCLOAK_REALM').'/users/'.$userId, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                ],
                'json' => $body,
                'verify' => false,
            ]);
        } catch (\Exception $e) {
            return 'Error: ' . $e->getMessage();
        }
    }

    public function massiveBlockFile(Request $request) {
        try {
            $request->validate([
                'file' => 'required|file|mimes:xls'
            ]);

            if($request->file('file')->isValid()){
                $path = $request->file('file')->store('uploads');

                $data = $this->processFile($path);
                $block = $this->massiveBlock($data);
            }

            return response()->json(['Proceso terminado'=> $block]);
        } catch (\Throwable $th) {
            return response()->json(['error' => $th], 400);
        }

    }

    public function processFile($path) {
        $data = Excel::toArray([], storage_path('app/' . $path));

        $headers = $data[0][0];
        $rows = array_slice($data[0], 1);

        $userIndex = array_search('USUARIO', $headers);

        $usersList = [];

        foreach ($rows as $row) {
            $user = $row[$userIndex];
            array_push($usersList, $user);
        }

        if ($usersList != []){
            return $usersList;
        } else {
            return response()->json(['message' => 'No hay usuarios para bloquear'], 400);
        }
    }

    public function massiveBlock($data) {
        try {
            $usersCollection = $data;
            $arrUsers = [];
            $usersList = [];

            foreach ($usersCollection as $user) {

                $idUser = $this->getIdUser($user);

                if ($idUser === "usuario inexistente") {
                    continue;
                }

                array_push($arrUsers, $idUser);

                $bloqUsuario = $this->blockUser($idUser);
                $block = true;

                if ($bloqUsuario['urn:ietf:params:scim:schemas:extension:enterprise:2.0:User']['accountState'] !== "LOCKED") {
                    $block = false;

                    $usersList[] = [
                        'Usuario' => $user,
                        'Id Usuario WSO2' => $idUser,
                        'isLock' => $block,
                        'Error' => 'Error al bloquear usuario: ' . $idUser
                    ];
                    continue;
                }

                $dataUser = [
                    'Usuario' => $user,
                    'Id Usuario WSO2' => $idUser,
                    'isLock' => $block
                ];

                array_push($usersList, $dataUser);
            }

            return $usersList;
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Error: ' . $e->getMessage()], 500);
        }
    }
}
