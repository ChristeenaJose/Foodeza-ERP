use std::path::PathBuf;
use std::process::{Child, Command};

#[cfg_attr(mobile, tauri::mobile_entry_point)]
pub fn run() {
    let _api = spawn_laravel_api();

    tauri::Builder::default()
        .plugin(tauri_plugin_shell::init())
        .run(tauri::generate_context!())
        .expect("error while running Foodeza ERP");
}

fn shared_data_dir() -> PathBuf {
    let home = std::env::var("HOME").unwrap_or_else(|_| "/tmp".into());
    PathBuf::from(home).join("Library/Application Support/com.foodeza.bookkeeping")
}

fn spawn_laravel_api() -> Option<Child> {
    let api_dir = resolve_api_dir()?;
    let data_dir = shared_data_dir();
    let _ = std::fs::create_dir_all(&data_dir);

    Command::new("php")
        .args([
            "artisan",
            "serve",
            "--host=127.0.0.1",
            "--port=17654",
            "--no-reload",
        ])
        .current_dir(&api_dir)
        .env("FOODEZA_DATA_DIR", data_dir.to_string_lossy().as_ref())
        .spawn()
        .ok()
}

fn resolve_api_dir() -> Option<PathBuf> {
    if let Ok(path) = std::env::var("FOODEZA_API_DIR") {
        let api = PathBuf::from(path);
        if api.join("artisan").exists() {
            return Some(api);
        }
    }

    if let Ok(exe) = std::env::current_exe() {
        let mut dir = exe.parent()?.to_path_buf();
        for _ in 0..10 {
            for candidate in [dir.join("api"), dir.join("Resources").join("api")] {
                if candidate.join("artisan").exists() {
                    return Some(candidate);
                }
            }
            dir = dir.parent()?.to_path_buf();
        }
    }

    if let Ok(cwd) = std::env::current_dir() {
        for rel in ["api", "../api"] {
            let api = cwd.join(rel);
            if api.join("artisan").exists() {
                return Some(api);
            }
        }
    }

    let home = std::env::var("HOME").ok()?;
    let default_api = PathBuf::from(home).join("Projects/foodeza/bookkeeping/api");
    if default_api.join("artisan").exists() {
        return Some(default_api);
    }

    None
}
