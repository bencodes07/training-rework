import { Link, Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Separator } from '@/components/ui/separator';
import { route } from 'ziggy-js';

interface LoginProps {
    canResetPassword?: boolean;
    status?: string;
}

export default function Login({ canResetPassword, status }: LoginProps) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();

        post(route('login.store'), {
            onFinish: () => reset('password'),
        });
    };

    const handleVatsimLogin = () => {
        window.location.href = route('auth.vatsim');
    };

    return (
        <>
            <Head title="Log in" />

            <div className="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
                <Card className="w-full max-w-md">
                    <CardHeader className="space-y-1">
                        <CardTitle className="text-2xl font-bold text-center">
                            Sign in to your account
                        </CardTitle>
                        <CardDescription className="text-center">
                            Access the VATGER Training System
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {status && (
                            <Alert>
                                <AlertDescription>{status}</AlertDescription>
                            </Alert>
                        )}

                        {errors.oauth && (
                            <Alert variant="destructive">
                                <AlertDescription>{errors.oauth}</AlertDescription>
                            </Alert>
                        )}

                        {/* VATSIM OAuth Button */}
                        <Button
                            type="button"
                            onClick={handleVatsimLogin}
                            className="w-full bg-blue-600 hover:bg-blue-700 text-white"
                            size="lg"
                        >
                            <svg 
                                className="w-5 h-5 mr-2" 
                                viewBox="0 0 24 24" 
                                fill="currentColor"
                            >
                                <path d="M12 2L13.09 8.26L22 9L13.09 9.74L12 16L10.91 9.74L2 9L10.91 8.26L12 2Z"/>
                            </svg>
                            Sign in with VATSIM
                        </Button>

                        <div className="relative">
                            <div className="absolute inset-0 flex items-center">
                                <Separator />
                            </div>
                            <div className="relative flex justify-center text-xs uppercase">
                                <span className="bg-background px-2 text-muted-foreground">
                                    Or continue with email
                                </span>
                            </div>
                        </div>

                        {/* Traditional Login Form */}
                        <form onSubmit={submit} className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="email">Email</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    value={data.email}
                                    className="block w-full"
                                    autoComplete="username"
                                    autoFocus
                                    onChange={(e) => setData('email', e.target.value)}
                                />
                                {errors.email && (
                                    <p className="text-sm text-red-600">{errors.email}</p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="password">Password</Label>
                                <Input
                                    id="password"
                                    type="password"
                                    name="password"
                                    value={data.password}
                                    className="block w-full"
                                    autoComplete="current-password"
                                    onChange={(e) => setData('password', e.target.value)}
                                />
                                {errors.password && (
                                    <p className="text-sm text-red-600">{errors.password}</p>
                                )}
                            </div>

                            <div className="flex items-center">
                                <input
                                    id="remember"
                                    type="checkbox"
                                    name="remember"
                                    checked={data.remember}
                                    onChange={(e) => setData('remember', e.target.checked)}
                                    className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                />
                                <Label htmlFor="remember" className="ml-2 block text-sm text-gray-900">
                                    Remember me
                                </Label>
                            </div>

                            <Button
                                type="submit"
                                className="w-full"
                                disabled={processing}
                            >
                                {processing ? 'Signing in...' : 'Sign in'}
                            </Button>

                            <div className="flex items-center justify-between text-sm">
                                {canResetPassword && (
                                    <Link
                                        href={route('password.request')}
                                        className="text-blue-600 hover:text-blue-500"
                                    >
                                        Forgot your password?
                                    </Link>
                                )}
                                
                                <Link
                                    href={route('register')}
                                    className="text-blue-600 hover:text-blue-500"
                                >
                                    Need an account?
                                </Link>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}