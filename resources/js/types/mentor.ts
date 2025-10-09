export interface SoloStatus {
  remaining: number;
  used: number;
  expiry: string;
}

export interface Trainee {
  id: number;
  name: string;
  vatsimId: string;
  initials: string;
  progress: boolean[];
  lastSession: string | null;
  nextStep: string;
  claimedBy: string | null;
  soloStatus: SoloStatus | null;
  remark: string;
}

export interface MentorCourse {
  id: number;
  name: string;
  position: string;
  type: string;
  activeTrainees: number;
  trainees: Trainee[];
}

export interface MentorStatistics {
  activeTrainees: number;
  claimedTrainees: number;
  trainingSessions: number;
  waitingList: number;
}