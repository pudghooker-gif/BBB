<?php 
namespace VanguardLTE\Http\Middleware
{
    class VerifyInstallation
    {
        public function handle($request, \Closure $next)
        {
            $installed = file_exists(base_path('.env')) || (bool) config('app.key');

            if( !$installed && !$request->is('install*') )
            {
                return redirect()->to('install');
            }
            if( $installed && $request->is('install*') && !$request->is('install/complete') )
            {
                throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
            }
            return $next($request);
        }
    }

}
