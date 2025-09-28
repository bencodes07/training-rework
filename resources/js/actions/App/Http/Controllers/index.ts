import EndorsementController from './EndorsementController'
import CourseController from './CourseController'
import WaitingListController from './WaitingListController'
import FamiliarisationController from './FamiliarisationController'
import Settings from './Settings'
import Auth from './Auth'

const Controllers = {
    EndorsementController: Object.assign(EndorsementController, EndorsementController),
    CourseController: Object.assign(CourseController, CourseController),
    WaitingListController: Object.assign(WaitingListController, WaitingListController),
    FamiliarisationController: Object.assign(FamiliarisationController, FamiliarisationController),
    Settings: Object.assign(Settings, Settings),
    Auth: Object.assign(Auth, Auth),
}

export default Controllers