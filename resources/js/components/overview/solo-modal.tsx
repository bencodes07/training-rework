import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Trainee } from '@/types/mentor';
import { router } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import { Loader2, AlertCircle, Clock, Calendar, Info, Trash } from 'lucide-react';

interface SoloModalProps {
    trainee: Trainee | null;
    courseId: number | null;
    isOpen: boolean;
    onClose: () => void;
}

export function SoloModal({ trainee, courseId, isOpen, onClose }: SoloModalProps) {
    const [mode, setMode] = useState<'none' | 'add' | 'extend' | 'remove'>('none');
    const [expiryDate, setExpiryDate] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [error, setError] = useState<string | null>(null);

    // Calculate default expiry date (30 days from now)
    useEffect(() => {
        if (isOpen && trainee) {
            const defaultDate = new Date();
            defaultDate.setDate(defaultDate.getDate() + 30);
            setExpiryDate(defaultDate.toISOString().split('T')[0]);
            
            setMode('none');
        }
    }, [isOpen, trainee]);

    const handleClose = () => {
        setMode('none');
        setError(null);
        setExpiryDate('');
        onClose();
    };

    const validateExpiryDate = (date: string): boolean => {
        const selectedDate = new Date(date);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        const maxDate = new Date();
        maxDate.setDate(maxDate.getDate() + 31);
        
        if (selectedDate < today) {
            setError('Expiry date cannot be in the past');
            return false;
        }
        
        if (selectedDate > maxDate) {
            setError('Solo endorsement cannot exceed 31 days from today');
            return false;
        }
        
        return true;
    };

    const handleAddSolo = () => {
        if (!trainee || !courseId || !expiryDate) return;
        
        if (!validateExpiryDate(expiryDate)) return;
        
        setIsSubmitting(true);
        setError(null);
        
        router.post(
            route('overview.add-solo'),
            {
                trainee_id: trainee.id,
                course_id: courseId,
                expiry_date: expiryDate,
            },
            {
                onSuccess: () => {
                    handleClose();
                },
                onError: (errors) => {
                    const errorMessage = Object.values(errors).flat()[0];
                    setError(typeof errorMessage === 'string' ? errorMessage : 'Failed to add solo');
                },
                onFinish: () => {
                    setIsSubmitting(false);
                },
            }
        );
    };

    const handleExtendSolo = () => {
        if (!trainee || !courseId || !expiryDate || !trainee.soloStatus) return;
        
        if (!validateExpiryDate(expiryDate)) return;
        
        setIsSubmitting(true);
        setError(null);
        
        router.post(
            route('overview.extend-solo'),
            {
                trainee_id: trainee.id,
                course_id: courseId,
                expiry_date: expiryDate,
            },
            {
                onSuccess: () => {
                    handleClose();
                },
                onError: (errors) => {
                    const errorMessage = Object.values(errors).flat()[0];
                    setError(typeof errorMessage === 'string' ? errorMessage : 'Failed to extend solo');
                },
                onFinish: () => {
                    setIsSubmitting(false);
                },
            }
        );
    };

    const handleRemoveSolo = () => {
        if (!trainee || !courseId) return;
        
        setIsSubmitting(true);
        setError(null);
        
        router.post(
            route('overview.remove-solo'),
            {
                trainee_id: trainee.id,
                course_id: courseId,
            },
            {
                onSuccess: () => {
                    handleClose();
                },
                onError: (errors) => {
                    const errorMessage = Object.values(errors).flat()[0];
                    setError(typeof errorMessage === 'string' ? errorMessage : 'Failed to remove solo');
                },
                onFinish: () => {
                    setIsSubmitting(false);
                },
            }
        );
    };

    return (
        <Dialog open={isOpen} onOpenChange={handleClose}>
            <DialogContent className="max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        Solo Endorsement - {trainee?.name}
                    </DialogTitle>
                    <DialogDescription>
                        {trainee?.soloStatus
                            ? 'Manage the solo endorsement for this trainee'
                            : 'Grant a solo endorsement to this trainee'}
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-6 py-4">
                    {/* Current Solo Status */}
                    {trainee?.soloStatus && (
                        <div className="rounded-lg border bg-muted/50 p-4">
                            <h3 className="mb-3 font-medium">Current Solo Status</h3>
                            <div className="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <p className="text-muted-foreground">Remaining Days:</p>
                                    <p className="text-2xl font-semibold">{trainee.soloStatus.remaining}</p>
                                    <p className="text-xs text-muted-foreground">Until expiry</p>
                                </div>
                                <div>
                                    <p className="text-muted-foreground">Used Solo Days:</p>
                                    <p className="text-2xl font-semibold">{trainee.soloStatus.used}</p>
                                    <p className="text-xs text-muted-foreground">Days since creation</p>
                                </div>
                                <div className="col-span-2 border-t pt-3">
                                    <p className="text-muted-foreground">Expiry Date:</p>
                                    <p className="font-semibold">
                                        {new Date(trainee.soloStatus.expiry).toLocaleDateString('de')}
                                    </p>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Info Alert */}
                    {mode === 'none' && !trainee?.soloStatus && (
                        <Alert>
                            <Info className="h-4 w-4" />
                            <AlertDescription>
                                Solo endorsements allow trainees to control a position independently for training purposes.
                                They can be granted for a maximum of 31 days at a time.
                            </AlertDescription>
                        </Alert>
                    )}

                    {/* Action Selection */}
                    {mode === 'none' && (
                        <div className="space-y-3">
                            {!trainee?.soloStatus ? (
                                <Button onClick={() => setMode('add')} className="w-full">
                                    <Calendar className="mr-2 h-4 w-4" />
                                    Add Solo Endorsement
                                </Button>
                            ) : (
                                <>
                                    <Button onClick={() => setMode('extend')} className="w-full" variant="default">
                                        <Clock className="size-4" />
                                        Extend Solo Endorsement
                                    </Button>
                                    <Button onClick={() => setMode('remove')} className="w-full" variant="destructive">
                                        <Trash className='size-4' />
                                        Remove Solo Endorsement
                                    </Button>
                                </>
                            )}
                        </div>
                    )}

                    {/* Add/Extend Solo Form */}
                    {(mode === 'add' || mode === 'extend') && (
                        <div className="space-y-4">
                            <Alert>
                                <AlertCircle className="h-4 w-4" />
                                <AlertDescription>
                                    Solo endorsements can be {mode === 'add' ? 'granted' : 'extended'} for a maximum of 31 days at a time.
                                </AlertDescription>
                            </Alert>

                            <div className="space-y-2">
                                <Label htmlFor="expiry-date">
                                    {mode === 'add' ? 'Expiry Date' : 'New Expiry Date'}
                                </Label>
                                <Input
                                    id="expiry-date"
                                    type="date"
                                    value={expiryDate}
                                    onChange={(e) => {
                                        setExpiryDate(e.target.value);
                                        setError(null);
                                    }}
                                    min={new Date().toISOString().split('T')[0]}
                                    max={new Date(Date.now() + 31 * 24 * 60 * 60 * 1000).toISOString().split('T')[0]}
                                />
                                <p className="text-xs text-muted-foreground">
                                    {mode === 'add' 
                                        ? 'Select when this solo endorsement will expire (maximum 31 days from today)'
                                        : 'Select new expiry date to extend the solo endorsement (maximum 31 days from today)'}
                                </p>
                            </div>

                            {error && (
                                <Alert variant="destructive">
                                    <AlertCircle className="h-4 w-4" />
                                    <AlertDescription>{error}</AlertDescription>
                                </Alert>
                            )}

                            <div className="flex gap-2">
                                <Button
                                    variant="outline"
                                    onClick={() => setMode('none')}
                                    disabled={isSubmitting}
                                    className="flex-1"
                                >
                                    Cancel
                                </Button>
                                <Button
                                    onClick={mode === 'add' ? handleAddSolo : handleExtendSolo}
                                    disabled={isSubmitting || !expiryDate}
                                    className="flex-1"
                                >
                                    {isSubmitting ? (
                                        <>
                                            <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                            {mode === 'add' ? 'Adding...' : 'Extending...'}
                                        </>
                                    ) : (
                                        <>{mode === 'add' ? 'Add Solo' : 'Extend Solo'}</>
                                    )}
                                </Button>
                            </div>
                        </div>
                    )}

                    {/* Remove Solo Confirmation */}
                    {mode === 'remove' && (
                        <div className="space-y-4">
                            <Alert variant="destructive">
                                <AlertCircle className="h-4 w-4" />
                                <AlertDescription>
                                    Are you sure you want to remove this solo endorsement? This action cannot be undone.
                                    The trainee will immediately lose their solo privileges.
                                </AlertDescription>
                            </Alert>

                            {error && (
                                <Alert variant="destructive">
                                    <AlertCircle className="h-4 w-4" />
                                    <AlertDescription>{error}</AlertDescription>
                                </Alert>
                            )}

                            <div className="flex gap-2">
                                <Button
                                    variant="outline"
                                    onClick={() => setMode('none')}
                                    disabled={isSubmitting}
                                    className="flex-1"
                                >
                                    Cancel
                                </Button>
                                <Button
                                    onClick={handleRemoveSolo}
                                    disabled={isSubmitting}
                                    variant="destructive"
                                    className="flex-1"
                                >
                                    {isSubmitting ? (
                                        <>
                                            <Loader2 className="mr-2 h-4 w-4" animate-spin />
                                            Removing...
                                        </>
                                    ) : (
                                        'Confirm Removal'
                                    )}
                                </Button>
                            </div>
                        </div>
                    )}
                </div>

                {mode === 'none' && (
                    <DialogFooter>
                        <Button variant="outline" onClick={handleClose}>
                            Close
                        </Button>
                    </DialogFooter>
                )}
            </DialogContent>
        </Dialog>
    );
}