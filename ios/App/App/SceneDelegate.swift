import UIKit
import Capacitor
import LocalAuthentication

class SceneDelegate: UIResponder, UIWindowSceneDelegate {
    var window: UIWindow?

    private var lockWindow: UIWindow?
    private var isLocked = true
    private var isAuthenticating = false
    private var backgroundedAt: Date?
    // Re-lock only if the app stayed in background longer than this (seconds)
    private let relockGracePeriod: TimeInterval = 60

    func scene(_ scene: UIScene, willConnectTo session: UISceneSession, options connectionOptions: UIScene.ConnectionOptions) {
        guard let windowScene = scene as? UIWindowScene else { return }

        window = UIWindow(windowScene: windowScene)
        window?.rootViewController = CAPBridgeViewController()
        window?.makeKeyAndVisible()

        showLockScreen(in: windowScene)

        SceneDelegateProxy.shared.scene(scene, willConnectTo: session, options: connectionOptions)
    }

    func scene(_ scene: UIScene, openURLContexts URLContexts: Set<UIOpenURLContext>) {
        SceneDelegateProxy.shared.scene(scene, openURLContexts: URLContexts)
    }

    func scene(_ scene: UIScene, continue userActivity: NSUserActivity) {
        SceneDelegateProxy.shared.scene(scene, continue: userActivity)
    }

    func sceneDidEnterBackground(_ scene: UIScene) {
        guard let windowScene = scene as? UIWindowScene else { return }
        backgroundedAt = Date()
        // Cover content so the app switcher snapshot doesn't show it
        showLockScreen(in: windowScene)
    }

    func sceneWillEnterForeground(_ scene: UIScene) {
        if !isLocked, let since = backgroundedAt, Date().timeIntervalSince(since) < relockGracePeriod {
            hideLockScreen()
        } else {
            isLocked = true
        }
        backgroundedAt = nil
    }

    func sceneDidBecomeActive(_ scene: UIScene) {
        if isLocked {
            authenticate()
        }
    }

    // MARK: - Face ID lock

    private func showLockScreen(in windowScene: UIWindowScene) {
        if lockWindow != nil { return }
        let lockVC = LockViewController()
        lockVC.onUnlockTapped = { [weak self] in self?.authenticate() }

        let w = UIWindow(windowScene: windowScene)
        w.windowLevel = .alert + 1
        w.rootViewController = lockVC
        w.isHidden = false
        lockWindow = w
    }

    private func hideLockScreen() {
        UIView.animate(withDuration: 0.25, animations: {
            self.lockWindow?.alpha = 0
        }, completion: { _ in
            self.lockWindow?.isHidden = true
            self.lockWindow = nil
            self.window?.makeKeyAndVisible()
        })
    }

    private func authenticate() {
        guard !isAuthenticating else { return }

        let context = LAContext()
        context.localizedCancelTitle = "Anulează"
        var error: NSError?
        // Device has no Face ID / Touch ID / passcode configured: don't lock the user out
        guard context.canEvaluatePolicy(.deviceOwnerAuthentication, error: &error) else {
            isLocked = false
            hideLockScreen()
            return
        }

        isAuthenticating = true
        context.evaluatePolicy(.deviceOwnerAuthentication, localizedReason: "Deblochează Kalo Mind") { success, _ in
            DispatchQueue.main.async {
                self.isAuthenticating = false
                if success {
                    self.isLocked = false
                    self.hideLockScreen()
                }
            }
        }
    }
}

private class LockViewController: UIViewController {
    var onUnlockTapped: (() -> Void)?

    override func viewDidLoad() {
        super.viewDidLoad()
        view.backgroundColor = UIColor(red: 0x0A / 255, green: 0x0E / 255, blue: 0x1A / 255, alpha: 1)

        let icon = UIImageView(image: UIImage(systemName: "faceid"))
        icon.tintColor = .white
        icon.contentMode = .scaleAspectFit
        icon.preferredSymbolConfiguration = UIImage.SymbolConfiguration(pointSize: 64, weight: .light)

        let title = UILabel()
        title.text = "Kalo Mind este blocat"
        title.textColor = .white
        title.font = .systemFont(ofSize: 20, weight: .semibold)

        var config = UIButton.Configuration.filled()
        config.title = "Deblochează"
        config.cornerStyle = .capsule
        config.baseBackgroundColor = UIColor.white.withAlphaComponent(0.15)
        config.baseForegroundColor = .white
        config.contentInsets = NSDirectionalEdgeInsets(top: 12, leading: 28, bottom: 12, trailing: 28)
        let button = UIButton(configuration: config, primaryAction: UIAction { [weak self] _ in
            self?.onUnlockTapped?()
        })

        let stack = UIStackView(arrangedSubviews: [icon, title, button])
        stack.axis = .vertical
        stack.alignment = .center
        stack.spacing = 20
        stack.setCustomSpacing(32, after: title)
        stack.translatesAutoresizingMaskIntoConstraints = false
        view.addSubview(stack)

        NSLayoutConstraint.activate([
            stack.centerXAnchor.constraint(equalTo: view.centerXAnchor),
            stack.centerYAnchor.constraint(equalTo: view.centerYAnchor),
        ])
    }
}
