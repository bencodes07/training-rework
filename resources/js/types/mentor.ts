export interface MentorStatistics {
    activeTrainees: number;
    claimedTrainees: number;
    trainingSessions: number;
    waitingList: number;
}

export interface RemarkData {
    text: string;
    updated_at: string | null;
    author_initials: string | null;
    author_name: string | null;
}

export interface SoloStatus {
    remaining: number;
    used: number;
    expiry: string;
}

export interface Trainee {
    id: number;
    name: string;
    vatsimId: number;
    initials: string;
    progress: boolean[];
    lastSession: string | null;
    nextStep: string;
    claimedBy: string | null;
    soloStatus: SoloStatus | null;
    remark: RemarkData | null;
}

export interface MentorCourse {
    id: number;
    name: string;
    position: string;
    type: string;
    activeTrainees: number;
    trainees: Trainee[];
}
