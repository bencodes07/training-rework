import { Head } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Alert, AlertDescription } from '@/components/ui/alert';

interface LoginProps {
    status?: string;
}

export default function Login({ status }: LoginProps) {
    const handleVatsimLogin = () => {
        window.location.href = '/auth/vatsim';
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
                            Access the VATGER Training System with your VATSIM account
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {status && (
                            <Alert>
                                <AlertDescription>{status}</AlertDescription>
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

                        <div className="text-center text-sm text-gray-600 mt-4">
                            <p>Only VATSIM members can access this system.</p>
                            <p className="mt-1">
                                Don't have a VATSIM account? {' '}
                                <a 
                                    href="https://my.vatsim.net/join" 
                                    target="_blank" 
                                    rel="noopener noreferrer"
                                    className="text-blue-600 hover:text-blue-500 underline"
                                >
                                    Join VATSIM
                                </a>
                            </p>
                        </div>

                        {/* Debug/Admin access hint for development */}
                        {process.env.NODE_ENV === 'development' && (
                            <div className="mt-6 p-3 bg-yellow-50 border border-yellow-200 rounded-md">
                                <p className="text-xs text-yellow-800">
                                    <strong>Development Mode:</strong> Admin access available at {' '}
                                    <a href="/admin/login" className="underline">/admin/login</a>
                                </p>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}