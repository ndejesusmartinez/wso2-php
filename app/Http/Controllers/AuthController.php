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
            $response = $client->post(env('WSO2_TOKEN_URL'), [
                'form_params' => [
                    'grant_type' => 'password',
                    'username' => $request->username,
                    'password' => $request->password,
                    'client_id' => env('WSO2_CLIENT_ID'),
                    'client_secret' => env('WSO2_CLIENT_SECRET'),
                ],
                'verify' => false
            ]);

            $data = json_decode($response->getBody(), true);
            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Unauthorized'], 401);
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
                    'emails' => [
                        'value' => $request->email,
                        'primary' => true,
                    ],
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
                    'grant_type' => 'password', 
                    'username' => 'admin', 
                    'password' => 'admin', 
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
}
