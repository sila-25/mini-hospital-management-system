import os
import json

# Absolute path to your project
BASE_DIR = r"C:\xampp\htdocs\veecare_medical_centre"

# Optional: ignore unnecessary files/folders
IGNORE = {"__pycache__", "extract.py", "structure.json", ".git"}

def extract_structure(path):
    structure = {}

    try:
        for item in os.listdir(path):
            if item in IGNORE:
                continue

            full_path = os.path.join(path, item)

            if os.path.isdir(full_path):
                structure[item] = extract_structure(full_path)
            else:
                structure.setdefault("files", []).append(item)

    except Exception as e:
        structure["error"] = str(e)

    return structure

def save_to_json(data):
    output_file = os.path.join(BASE_DIR, "structure.json")
    with open(output_file, "w") as f:
        json.dump(data, f, indent=4)

if __name__ == "__main__":
    data = extract_structure(BASE_DIR)
    save_to_json(data)
    print("✅ Structure extracted to structure.json successfully.")