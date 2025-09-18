import { usePage } from '@inertiajs/react';
import { Shield, User } from 'lucide-react';

export default function UserTypeIndicator() {
    const { auth } = usePage().props as any;
    const user = auth.user;

    if (!user) return null;

    if (user.is_admin) {
        return (
            <div className="flex items-center gap-2 px-3 py-1 bg-red-100 text-red-700 rounded-full text-sm font-medium">
                <Shield className="w-4 h-4" />
                Admin Access
            </div>
        );
    }

    if (user.is_vatsim_user) {
        return (
            <div className="flex items-center gap-2 px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-sm font-medium">
                <User className="w-4 h-4" />
                VATSIM: {user.vatsim_id}
            </div>
        );
    }

    return null;
}