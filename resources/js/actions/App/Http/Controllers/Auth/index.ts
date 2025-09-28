import AuthenticatedSessionController from './AuthenticatedSessionController'
import VatsimOAuthController from './VatsimOAuthController'
import AdminAuthController from './AdminAuthController'

const Auth = {
    AuthenticatedSessionController: Object.assign(AuthenticatedSessionController, AuthenticatedSessionController),
    VatsimOAuthController: Object.assign(VatsimOAuthController, VatsimOAuthController),
    AdminAuthController: Object.assign(AdminAuthController, AdminAuthController),
}

export default Auth