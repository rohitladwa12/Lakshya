import re

# File paths
gmu_file = r"c:/Users/Rohit Ladwa/Downloads/ac_discipline_master.sql"
gmit_file = r"c:/Users/Rohit Ladwa/Downloads/ac_discipline_master (1).sql"
output_file = r"c:/xampp/htdocs/Lakshya/database/dept_coordinators_update.sql"

# Explicit map from PHP code
php_map = {
    'CSE-AIML': 'AIML',
    'CSE-CSE': 'CSE',
    'CSE-DS': 'DS',
    'CSE-IOT': 'IOT',
    'CSE-CS': 'CS',
}

def parse_sql_values(filepath, index):
    disciplines = set()
    with open(filepath, 'r', encoding='utf-8', errors='ignore') as f:
        content = f.read()
    
    lines = content.split('\n')
    for line in lines:
        line = line.strip()
        if not line.startswith('('): continue
        if line.endswith(',') or line.endswith(';'): line = line[:-1]
        if line.startswith('(') and line.endswith(')'):
            # Simple split by comma, ignoring complexity since we know the format
            # Using a regex to split by comma outside quotes might be safer but simple split usually works for these dumps
            # We'll use a safer csv approach
            import csv
            from io import StringIO
            reader = csv.reader(StringIO(line[1:-1]), quotechar="'", skipinitialspace=True)
            try:
                row = next(reader)
                if len(row) > index:
                    disc = row[index].strip()
                    if disc:
                        disciplines.add(disc)
            except:
                pass
    return disciplines

# GMU is index 11
gmu_disciplines = parse_sql_values(gmu_file, 11)
# GMIT is index 4
gmit_disciplines = parse_sql_values(gmit_file, 4)

# Logic to merge
final_coordinators = [] # List of unique department names
covered_gmit = set()

# 1. Process GMU items
for g_code in gmu_disciplines:
    final_coordinators.append(g_code)
    
    # Calculate what GMIT code this covers
    mapped = None
    if g_code in php_map:
        mapped = php_map[g_code]
    elif g_code.startswith('CSE-'):
        mapped = g_code[4:]
    else:
        mapped = g_code
        
    if mapped:
        covered_gmit.add(mapped)

# 2. Process GMIT items not covered
for t_code in gmit_disciplines:
    if t_code not in covered_gmit:
        # If not covered, adds it. 
        # Check if t_code exist in GMU directly? 
        # If t_code ('CSE') exists in GMU, it would be in gmu_disciplines 
        # and covered via `mapped = g_code` case.
        # So this only adds truly unique GMIT codes (e.g. D.PHARM)
        final_coordinators.append(t_code)

# Generate SQL
sql_output = ["-- Auto-generated Department Coordinators", "INSERT INTO dept_coordinators (email, password, full_name, department, institution) VALUES"]
password_hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'

values = []
for disc in sorted(list(set(final_coordinators))):
    # Email generation: sanitized, lowercase
    # User requested format: ...coord.coordinator@placement
    email_disc = re.sub(r'[^a-zA-Z0-9]', '.', disc.lower())
    email = f"{email_disc}.coordinator@placement"
    name = f"{disc} Coordinator"
    
    val = f"('{email}', '{password_hash}', '{name}', '{disc}', 'GMU')"
    values.append(val)

# Batch join with commas
sql_output.append(",\n".join(values) + ";")

with open(output_file, 'w') as f:
    f.write('\n'.join(sql_output))

print(f"Generated {len(values)} coordinators.")
