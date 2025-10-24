// types/mentor.ts
// Updated to match Laravel backend structure with correct type codes

export interface Trainee {
    id: number;
    name: string;
    initials: string;
    vatsimId: string;
    progress: boolean[]; // Array of pass/fail for each session
    lastSession: string | null; // ISO date string
    nextStep: string | null;
    claimedBy: string | null; // "You" or mentor name, null if unclaimed
    claimedByMentorId: number | null;

    // Solo status for rating courses (RTG)
    soloStatus: {
        remaining: number;
        used: number;
        expiry: string; // YYYY-MM-DD format
    } | null;

    // Remark data from course_trainees pivot table
    remark: {
        text: string;
        updated_at: string | null; // ISO date string
        author_initials: string | null;
        author_name: string | null;
    } | null;

    // New properties for different course types
    // For ground/visitor courses (GST)
    endorsementStatus?: string | null; // e.g., "Issued", null

    // For endorsement courses (EDMT)
    moodleStatus?: 'completed' | 'in-progress' | 'not-started' | null;
}

export interface MentorCourse {
    id: number;
    name: string;
    position: string; // e.g., "TWR", "APP", "CTR", "GND"
    type: 'EDMT' | 'RTG' | 'GST' | 'FAM' | 'RST';
    soloStation?: string;

    activeTrainees: number; // Count of active trainees
    trainees: Trainee[];
}

export interface MentorStatistics {
    activeTrainees: number;
    claimedTrainees: number;
    trainingSessions: number;
    waitingList: number;
}

// For the mentor overview page
export interface MentorOverviewProps {
    courses: MentorCourse[];
    statistics: MentorStatistics;
}

// For available mentors in a course
export interface Mentor {
    id: number;
    name: string;
    vatsim_id: string;
    email: string;
}

// For assign trainee modal
export interface AssignTraineeData {
    trainee_id: number;
    course_id: number;
    mentor_id: number;
}

// For claim trainee
export interface ClaimTraineeData {
    trainee_id: number;
    course_id: number;
}

// For unclaim trainee
export interface UnclaimTraineeData {
    trainee_id: number;
    course_id: number;
}

// For remove trainee
export interface RemoveTraineeData {
    trainee_id: number;
    course_id: number;
}

// For update remark
export interface UpdateRemarkData {
    trainee_id: number;
    course_id: number;
    remark: string;
}

// Helper function to get display name for course type
export function getCourseTypeDisplay(type: MentorCourse['type']): string {
    const typeMap: Record<MentorCourse['type'], string> = {
        EDMT: 'Endorsement',
        RTG: 'Rating',
        GST: 'Visitor',
        FAM: 'Familiarisation',
        RST: 'Roster Reentry',
    };
    return typeMap[type] || type;
}

// Helper to determine which columns to show
export function getVisibleColumnsForCourseType(type: MentorCourse['type']) {
    return {
        solo: type === 'RTG', // Rating courses show solo
        endorsement: type === 'GST', // Ground/Visitor courses show endorsement
        moodleStatus: type === 'EDMT', // Endorsement courses show moodle
    };
}
