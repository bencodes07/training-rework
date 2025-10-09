import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import { Trainee } from '@/types/mentor';

interface RemarkDialogProps {
    trainee: Trainee | null;
    isOpen: boolean;
    onClose: () => void;
    remarkText: string;
    onRemarkChange: (text: string) => void;
    onSave: () => void;
}

export function RemarkDialog({ trainee, isOpen, onClose, remarkText, onRemarkChange, onSave }: RemarkDialogProps) {
    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Update Remark - {trainee?.name}</DialogTitle>
                    <DialogDescription>Add notes about this trainee's availability, performance, or other relevant information.</DialogDescription>
                </DialogHeader>
                <Textarea
                    placeholder="Enter remarks about this trainee..."
                    value={remarkText}
                    onChange={(e) => onRemarkChange(e.target.value)}
                    rows={4}
                />
                <DialogFooter>
                    <Button variant="outline" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button onClick={onSave}>Save</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}