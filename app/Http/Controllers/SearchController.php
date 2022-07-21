<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SearchController extends Controller
{
    private $loggedUser;

    public function __construct()
    {
        $this->middleware('auth:api');
        $this->loggedUser = auth()->user();
    }

    public function search(Request $request)
    {
        // GET api/search (txt)
        $array = ['error' => '', 'users' => []];

        $txt = $request->input('txt');

        if ($txt) {

            // Busca de usuários
            $userList = User::where('name', 'like', "%{$txt}%")->get();
            foreach ($userList as $userItem) {
                $array['users'][] = [
                    'id' => $userItem['id'],
                    'name' => $userItem['name'],
                    'avatar' => url('storage/media/avatars/' . $userItem['avatar'])
                ];
            }
        } else {
            $array['error'] = 'Digite alguma coisa para buscar.';
            return $array;
        }

        return $array;
    }
}
