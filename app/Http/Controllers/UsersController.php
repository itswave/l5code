<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class UsersController extends Controller
{
    /**
     * UsersController constructor.
     */
    public function __construct()
    {
        $this->middleware('guest');
    }

    /**
     * Show the application registration form.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('users.create');
    }

    /**
     * Handle a registration request for the application.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if ($socialUser = \App\User::socialUser($request->get('email'))->first()) {
            return $this->updateSocialAccount($request, $socialUser);
        }

        return $this->createNativeAccount($request);
    }

    /**
     * Confirm user's email address.
     *
     * @param string $code
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function confirm($code)
    {
        $user = \App\User::whereConfirmCode($code)->first();

        if (! $user) {
            return $this->respondWrongUrl();
        }

        $user->activated = 1;
        $user->confirm_code = null;
        $user->save();

        return $this->respondConfirmed($user);
    }

    /**
     * A user has logged into the application with social account before.
     * But s/he tries to register an native account again.
     * So updating his/her existing social account with the information.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\User $user
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    protected function updateSocialAccount(Request $request, \App\User $user)
    {
        $this->validate($request, [
            'name' => 'required|max:255',
            'email' => 'required|email|max:255',
            'password' => 'required|confirmed|min:6',
        ]);

        $user->update([
            'name' => $request->input('name'),
            'password' => bcrypt($request->input('password')),
        ]);

        return $this->respondUpdated($user);
    }

    /**
     * A user tries to register a native account for the first time.
     * S/he has not logged into this service before with a social account.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function createNativeAccount(Request $request)
    {
        $this->validate($request, [
            'name'  => 'required|max:255',
            'email'  => 'required|email|max:255|unique:users',
            'password' => 'required|confirmed|min:6',
        ]);

        $confirmCode = str_random(60);

        $user = \App\User::create([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'password' => bcrypt($request->input('password')),
            'confirm_code' => $confirmCode,
        ]);

        event(new \App\Events\UserCreated($user));

        return $this->respondConfirmationEmailSent();
    }

    /**
     * Make an error response.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    protected function respondWrongUrl()
    {
        flash()->error(trans('auth.users.error_wrong_url'));

        return redirect('/');
    }

    /**
     * Make a success response.
     *
     * @param \App\User $user
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    protected function respondConfirmed(\App\User $user)
    {
        return $this->respondSuccess(
            $user,
            trans('auth.users.info_confirmed', ['name' => $user->name])
        );
    }

    /**
     * Make a success response.
     *
     * @param \App\User $user
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    protected function respondUpdated(\App\User $user)
    {
        return $this->respondSuccess(
            $user,
            trans('auth.users.info_welcome', ['name' => $user->name])
        );
    }

    /**
     * Make a success response.
     *
     * @param \App\User $user
     * @param null $message
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    protected function respondSuccess(\App\User $user, $message = null)
    {
        auth()->login($user);
        flash($message);

        return ($return = request('return'))
            ? redirect(urldecode($return))
            : redirect()->intended();
    }

    /**
     * Make a success response.
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    protected function respondConfirmationEmailSent()
    {
        flash(trans('auth.users.info_confirmation_sent'));

        return redirect('/');
    }
}
