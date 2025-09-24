import { Badge } from '@/components/ui/badge';
import { Card, CardAction, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { PlaceholderPattern } from '@/components/ui/placeholder-pattern';
import UserTypeIndicator from '@/components/user-type-indicator';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { TrendingUp } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

export default function Dashboard() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="grid auto-rows-min gap-4 md:grid-cols-3">
                    <Card className="@container/card">
                        <CardHeader>
                            <CardDescription>Active Courses</CardDescription>
                            <CardTitle className="text-2xl font-semibold tabular-nums @[250px]/card:text-3xl">3</CardTitle>
                            <CardAction>
                                <Badge>Total: 5</Badge>
                            </CardAction>
                        </CardHeader>
                        <CardFooter className="text-sm">
                            <div className="text-muted-foreground">Courses you are currently enrolled in</div>
                        </CardFooter>
                    </Card>
                    <Card className="@container/card">
                        <CardHeader>
                            <CardDescription>Total Training Sessions</CardDescription>
                            <CardTitle className="text-2xl font-semibold tabular-nums @[250px]/card:text-3xl">26</CardTitle>
                            <CardAction>
                                <Badge>
                                    <TrendingUp />
                                    +12.5%
                                </Badge>
                            </CardAction>
                        </CardHeader>
                        <CardFooter className="flex-col items-start gap-1.5 text-sm">
                            <div className="text-muted-foreground">Amount of total sessions you had together with a mentor</div>
                        </CardFooter>
                    </Card>
                    <Card className="@container/card">
                        <CardHeader>
                            <CardDescription>Total Revenue</CardDescription>
                            <CardTitle className="text-2xl font-semibold tabular-nums @[250px]/card:text-3xl">$1,250.00</CardTitle>
                            <CardAction>
                                <Badge>
                                    <TrendingUp />
                                    +12.5%
                                </Badge>
                            </CardAction>
                        </CardHeader>
                        <CardFooter className="text-sm text-muted-foreground">Visitors for the last 6 months</CardFooter>
                    </Card>
                </div>
                <div className="relative min-h-[100vh] flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 md:min-h-min dark:border-sidebar-border">
                    <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                    <UserTypeIndicator />
                </div>
            </div>
        </AppLayout>
    );
}
