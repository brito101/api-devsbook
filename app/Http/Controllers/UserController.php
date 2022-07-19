<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\User;
use App\Models\UserRelation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Image;

class UserController extends Controller
{
    private $loggedUser;

    public function __construct()
    {
        $this->middleware('auth:api');
        $this->loggedUser = auth()->user();
    }

    public function update(Request $request)
    {
        // PUT api/user (name, email, birthdate, city, work, password, password_confirm)
        $array = ['error' => ''];

        $name = $request->input('name');
        $email = $request->input('email');
        $birthday = $request->input('birthday');
        $city = $request->input('city');
        $work = $request->input('work');
        $password = $request->input('password');
        $password_confirm = $request->input('password_confirm');

        $user = User::find($this->loggedUser['id']);

        // NAME
        if ($name) {
            $user->name = $name;
        }

        // E-MAIL
        if ($email) {
            if ($email != $user->email) {
                $emailExists = User::where('email', $email)->count();
                if ($emailExists === 0) {
                    $user->email = $email;
                } else {
                    $array['error'] = 'E-mail já existe!';
                    return $array;
                }
            }
        }

        // BIRTHDATE
        if ($birthday) {
            if (strtotime($birthday) === false) {
                $array['error'] = 'Data de nascimento inválida!';
                return $array;
            }
            $user->birthday = $birthday;
        }

        // CITY
        if ($city) {
            $user->city = $city;
        }

        // WORK
        if ($work) {
            $user->work = $work;
        }

        // PASSWORD
        if ($password && $password_confirm) {
            if ($password === $password_confirm) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $user->password = $hash;
            } else {
                $array['error'] = 'As senhas não batem.';
                return $array;
            }
        }

        $user->save();

        return $array;
    }

    public function updateAvatar(Request $request)
    {
        $array = ['error' => ''];
        $allowedTypes = ['image/jpg', 'image/jpeg', 'image/png'];

        $image = $request->file('avatar');

        if ($image) {
            if (in_array($image->getClientMimeType(), $allowedTypes)) {

                $filename = md5(time() . rand(0, 9999)) . '.jpg';

                $destPath = public_path('/storage/media/avatars');

                $img = Image::make($image->path())
                    ->fit(200, 200)
                    ->save($destPath . '/' . $filename);

                $user = User::find($this->loggedUser['id']);
                $user->avatar = $filename;
                $user->save();

                $array['url'] = url('/storage/media/avatars/' . $filename);
            } else {
                $array['error'] = 'Arquivo não suportado!';
                return $array;
            }
        } else {
            $array['error'] = 'Arquivo não enviado!';
            return $array;
        }

        return $array;
    }

    public function updateCover(Request $request)
    {
        $array = ['error' => ''];
        $allowedTypes = ['image/jpg', 'image/jpeg', 'image/png'];

        $image = $request->file('cover');

        if ($image) {
            if (in_array($image->getClientMimeType(), $allowedTypes)) {

                $filename = md5(time() . rand(0, 9999)) . '.jpg';

                $destPath = public_path('/storage/media/covers');

                $img = Image::make($image->path())
                    ->fit(850, 310)
                    ->save($destPath . '/' . $filename);

                $user = User::find($this->loggedUser['id']);
                $user->cover = $filename;
                $user->save();

                $array['url'] = url('/storage/media/covers/' . $filename);
            } else {
                $array['error'] = 'Arquivo não suportado!';
                return $array;
            }
        } else {
            $array['error'] = 'Arquivo não enviado!';
            return $array;
        }

        return $array;
    }

    public function read($id = false)
    {
        $array = ['error' => ''];

        if ($id) {
            $info = User::find($id);
            if (!$info) {
                $array['error'] = 'Usuário inexistente!';
                return $array;
            }
        } else {
            $info = $this->loggedUser;
        }

        $info['avatar'] = url('storage/media/avatars/' . $info['avatar']);
        $info['cover'] = url('storage/media/covers/' . $info['cover']);

        $info['me'] = ($info['id'] == $this->loggedUser['id']) ? true : false;

        $dateFrom = new \DateTime($info['birthday']);
        $dateTo = new \DateTime('today');
        $info['age'] = $dateFrom->diff($dateTo)->y;

        $info['followers'] = UserRelation::where('user_to', $info['id'])->count();
        $info['following'] = UserRelation::where('user_from', $info['id'])->count();

        $info['photoCount'] = Post::where('id_user', $info['id'])
            ->where('type', 'photo')
            ->count();

        $hasRelation = UserRelation::where('user_from', $this->loggedUser['id'])
            ->where('user_to', $info['id'])
            ->count();
        $info['isFollowing'] = ($hasRelation > 0) ? true : false;

        $array['data'] = $info;

        return $array;
    }

    public function follow($id)
    {
        $array = ['error' => ''];

        if ($id == $this->loggedUser['id']) {
            $array['error'] = 'Você não pode seguir a si mesmo.';
            return $array;
        }

        $userExists = User::find($id);
        if ($userExists) {

            $relation = UserRelation::where('user_from', $this->loggedUser['id'])
                ->where('user_to', $id)
                ->first();

            if ($relation) {
                // parar de seguir
                $relation->delete();
            } else {
                // seguir
                $newRelation = new UserRelation();
                $newRelation->user_from = $this->loggedUser['id'];
                $newRelation->user_to = $id;
                $newRelation->save();
            }
        } else {
            $array['error'] = 'Usuário inexistente!';
            return $array;
        }

        return $array;
    }

    public function followers($id)
    {
        $array = ['error' => ''];

        $userExists = User::find($id);
        if ($userExists) {

            $followers = UserRelation::where('user_to', $id)->get();
            $following = UserRelation::where('user_from', $id)->get();

            $array['followers'] = [];
            $array['following'] = [];

            foreach ($followers as $item) {
                $user = User::find($item['user_from']);
                $array['followers'][] = [
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'avatar' => url('storage/media/avatars/' . $user['avatar'])
                ];
            }

            foreach ($following as $item) {
                $user = User::find($item['user_from']);
                $array['following'][] = [
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'avatar' => url('storage/media/avatars/' . $user['avatar'])
                ];
            }
        } else {
            $array['error'] = 'Usuário inexistente!';
            return $array;
        }

        return $array;
    }
}
