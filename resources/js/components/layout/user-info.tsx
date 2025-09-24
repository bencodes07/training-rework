import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import { type User } from '@/types';
import { usePage } from '@inertiajs/react';

export function UserInfo({ showEmail = false }: { user: User; showEmail?: boolean }) {
    const getInitials = useInitials();
    const { auth } = usePage().props as any;
    const user = auth.user;

    if (!user) return null;

    return (
        <>
            <Avatar className="h-8 w-8 overflow-hidden rounded-full">
                <AvatarImage src={user.avatar} alt={user.name} />
                {user.is_admin ? (
                    <>
                        <AvatarFallback className="rounded-full border border-red-500 bg-neutral-200 text-black dark:border-red-500 dark:bg-neutral-700 dark:text-white">
                            {getInitials(user.name)}
                        </AvatarFallback>
                    </>
                ) : (
                    <>
                        <AvatarFallback className="rounded-full text-black dark:border dark:bg-neutral-700 dark:text-white">
                            {getInitials(user.name)}
                        </AvatarFallback>
                    </>
                )}
            </Avatar>
            <div className="grid flex-1 text-left text-sm leading-tight">
                <span className="truncate font-medium">{user.name}</span>
                {showEmail && <span className="truncate text-xs text-muted-foreground">{user.email}</span>}
            </div>
        </>
    );
}
