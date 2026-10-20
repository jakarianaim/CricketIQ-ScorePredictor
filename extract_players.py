import pandas as pd
import numpy as np
import json
import os

print("Processing data/IPL.csv to clean, normalize and deduplicate official IPL venues...")

df = pd.read_csv('data/IPL.csv', usecols=[
    'match_id', 'date', 'innings', 'batter', 'bat_pos',
    'runs_batter', 'balls_faced', 'player_out', 'striker_out',
    'batting_team', 'bowling_team', 'venue'
])

def normalize_venue(v):
    if not isinstance(v, str): return "Unknown Venue"
    # Mumbai
    if 'Wankhede' in v: return 'Wankhede Stadium, Mumbai'
    if 'Brabourne' in v: return 'Brabourne Stadium, Mumbai'
    if 'DY Patil' in v: return 'DY Patil Stadium, Navi Mumbai'
    # Kolkata
    if 'Eden Gardens' in v: return 'Eden Gardens, Kolkata'
    # Bengaluru
    if 'Chinnaswamy' in v: return 'M Chinnaswamy Stadium, Bengaluru'
    # Chennai
    if 'Chidambaram' in v or 'Chepauk' in v: return 'MA Chidambaram Stadium (Chepauk), Chennai'
    # Delhi
    if 'Feroz Shah' in v or 'Arun Jaitley' in v: return 'Arun Jaitley Stadium, Delhi'
    # Ahmedabad
    if 'Narendra Modi' in v or 'Motera' in v or 'Sardar Patel' in v: return 'Narendra Modi Stadium, Ahmedabad'
    # Hyderabad
    if 'Rajiv Gandhi' in v: return 'Rajiv Gandhi International Stadium, Hyderabad'
    # Jaipur
    if 'Sawai Mansingh' in v: return 'Sawai Mansingh Stadium, Jaipur'
    # Mohali
    if 'Punjab Cricket' in v or 'IS Bindra' in v: return 'PCA IS Bindra Stadium, Mohali'
    # Mullanpur
    if 'Mullanpur' in v or 'Maharaja Yadavindra' in v: return 'Maharaja Yadavindra Singh Stadium, Mullanpur'
    # Lucknow
    if 'Ekana' in v or 'Atal Bihari' in v: return 'BRSABV Ekana Stadium, Lucknow'
    # Pune
    if 'Maharashtra Cricket' in v or 'Subrata Roy' in v: return 'MCA Stadium, Pune'
    # Dharamsala
    if 'Dharamsala' in v or 'Himachal' in v: return 'HPCA Stadium, Dharamshala'
    # Visakhapatnam
    if 'ACA-VDCA' in v or 'Rajasekhara' in v: return 'ACA-VDCA Stadium, Visakhapatnam'
    # Guwahati
    if 'Barsapara' in v: return 'Barsapara Stadium, Guwahati'
    # Indore
    if 'Holkar' in v: return 'Holkar Stadium, Indore'
    # Rajkot
    if 'Saurashtra' in v: return 'SCA Stadium, Rajkot'
    # Ranchi
    if 'JSCA' in v: return 'JSCA Stadium, Ranchi'
    # Raipur
    if 'Shaheed Veer' in v or 'Raipur' in v: return 'Shaheed Veer Narayan Singh Stadium, Raipur'
    # Cuttack
    if 'Barabati' in v: return 'Barabati Stadium, Cuttack'
    # Kanpur
    if 'Green Park' in v: return 'Green Park, Kanpur'
    # Nagpur
    if 'Vidarbha' in v or 'Jamtha' in v: return 'VCA Stadium, Nagpur'
    # Kochi
    if 'Nehru Stadium' in v: return 'Jawaharlal Nehru Stadium, Kochi'
    # UAE (IPL 2014, 2020, 2021)
    if 'Dubai' in v: return 'Dubai International Cricket Stadium (UAE)'
    if 'Sharjah' in v: return 'Sharjah Cricket Stadium (UAE)'
    if 'Zayed' in v: return 'Sheikh Zayed Stadium, Abu Dhabi (UAE)'
    # South Africa (IPL 2009)
    if 'Kingsmead' in v: return 'Kingsmead, Durban (IPL 2009)'
    if 'SuperSport' in v: return 'SuperSport Park, Centurion (IPL 2009)'
    if 'Wanderers' in v: return 'Wanderers Stadium, Johannesburg (IPL 2009)'
    if 'Newlands' in v: return 'Newlands, Cape Town (IPL 2009)'
    if 'St George' in v: return 'St George Park, Gqeberha (IPL 2009)'
    if 'Buffalo' in v: return 'Buffalo Park, East London (IPL 2009)'
    if 'Diamond' in v or 'De Beers' in v: return 'Diamond Oval, Kimberley (IPL 2009)'
    if 'OUTsurance' in v: return 'Mangaung Oval, Bloemfontein (IPL 2009)'
    return v

df['venue'] = df['venue'].apply(normalize_venue)
df['is_out'] = (df['player_out'] == df['batter']) | (df['striker_out'] == True)

match_innings = df.groupby(['batter', 'match_id', 'date', 'batting_team', 'bowling_team', 'venue']).agg(
    runs=('runs_batter', 'sum'),
    balls=('balls_faced', 'sum'),
    outs=('is_out', 'max'),
    bat_pos=('bat_pos', 'min'),
    innings=('innings', 'min')
).reset_index()

match_innings['date'] = pd.to_datetime(match_innings['date'], errors='coerce')
match_innings = match_innings.sort_values(['batter', 'date', 'match_id'])

# Clean unique venues and opponents ordered by frequency
venues_list = df['venue'].value_counts().index.tolist()
opponents_list = df['bowling_team'].dropna().value_counts().index.tolist()

franchise_aliases = {
    'Royal Challengers Bangalore': ['Royal Challengers Bangalore', 'Royal Challengers Bengaluru'],
    'Royal Challengers Bengaluru': ['Royal Challengers Bangalore', 'Royal Challengers Bengaluru'],
    'Rising Pune Supergiant': ['Rising Pune Supergiant', 'Rising Pune Supergiants'],
    'Rising Pune Supergiants': ['Rising Pune Supergiant', 'Rising Pune Supergiants'],
    'Delhi Daredevils': ['Delhi Daredevils', 'Delhi Capitals'],
    'Delhi Capitals': ['Delhi Daredevils', 'Delhi Capitals'],
    'Kings XI Punjab': ['Kings XI Punjab', 'Punjab Kings'],
    'Punjab Kings': ['Kings XI Punjab', 'Punjab Kings']
}

players = []

# Load full player names dictionary
names_file = 'data/player_names.json'
player_names = {}
if os.path.exists(names_file):
    with open(names_file, 'r', encoding='utf-8') as f:
        player_names = json.load(f)

for batter, group in match_innings.groupby('batter'):
    total_innings = len(group)
    total_runs = int(group['runs'].sum())
    total_balls = int(group['balls'].sum())

    career_sr = (total_runs / max(1, total_balls)) * 100.0
    career_avg_per_inn = total_runs / max(1, total_innings)

    recent_group = group.tail(min(10, len(group)))
    recent_runs = recent_group['runs'].sum()
    recent_balls = recent_group['balls'].sum()
    recent_sr = (recent_runs / max(1, recent_balls)) * 100.0 if recent_balls > 0 else career_sr
    recent_avg = recent_group['runs'].mean()

    # Per-venue averages on cleaned venues
    v_dict = group.groupby('venue')['runs'].mean().round(2).to_dict()
    # Per-opponent averages
    o_dict = group.groupby('bowling_team')['runs'].mean().round(2).to_dict()

    venue_avg = sum(v_dict.values()) / max(1, len(v_dict)) if len(v_dict) else career_avg_per_inn
    opp_avg = sum(o_dict.values()) / max(1, len(o_dict)) if len(o_dict) else career_avg_per_inn

    pos_mode = group['bat_pos'].mode()
    bat_pos = int(pos_mode.iloc[0]) if len(pos_mode) else 4
    if bat_pos < 1: bat_pos = 1
    if bat_pos > 11: bat_pos = 11

    inn_mode = group['innings'].mode()
    innings = int(inn_mode.iloc[0]) if len(inn_mode) else 1
    if innings not in (1, 2): innings = 1

    is_tailender = 1 if bat_pos >= 8 else 0

    full_name = player_names.get(str(batter), str(batter))

    # Calculate teams played for and teams never faced (cannot be opponent)
    b_teams = set(group['batting_team'].dropna().unique())
    faced_teams = set(group['bowling_team'].dropna().unique())
    never_faced = b_teams - faced_teams

    invalid_opponents = set(never_faced)
    for t in list(never_faced):
        if t in franchise_aliases:
            for alt in franchise_aliases[t]:
                if alt not in faced_teams:
                    invalid_opponents.add(alt)

    # If player played for a single franchise lifetime:
    if len(b_teams) == 1:
        single_team = list(b_teams)[0]
        invalid_opponents.add(single_team)
        if single_team in franchise_aliases:
            for alt in franchise_aliases[single_team]:
                if alt not in faced_teams:
                    invalid_opponents.add(alt)

    players.append({
        'name': full_name,
        'short_name': str(batter),
        'teams': sorted(list(b_teams)),
        'invalid_opponents': sorted(list(invalid_opponents)),
        'recent_average': round(float(recent_avg), 2),
        'recent_strike_rate': round(float(recent_sr), 2),
        'venue_average': round(float(venue_avg), 2),
        'opponent_average': round(float(opp_avg), 2),
        'bat_pos': bat_pos,
        'innings': innings,
        'is_tailender': is_tailender,
        'total_runs': total_runs,
        'total_innings': total_innings,
        'venues': v_dict,
        'opponents': o_dict
    })

players.sort(key=lambda p: p['total_runs'], reverse=True)

# Overall historical venue benchmarks (runs per wicket/batter at each venue)
venue_benchmarks = (df.groupby('venue')['runs_batter'].sum() / df.groupby('venue')['is_out'].sum().replace(0, 1)).round(2).to_dict()
# Overall historical opponent bowling benchmarks (runs conceded per wicket/batter)
opponent_benchmarks = (df.groupby('bowling_team')['runs_batter'].sum() / df.groupby('bowling_team')['is_out'].sum().replace(0, 1)).round(2).to_dict()
league_average = round(float(df['runs_batter'].sum() / max(1, df['is_out'].sum())), 2)

data_payload = {
    'venues': venues_list,
    'opponents': opponents_list,
    'venue_benchmarks': venue_benchmarks,
    'opponent_benchmarks': opponent_benchmarks,
    'league_average': league_average,
    'players': players
}

output_path = 'data/players.json'
with open(output_path, 'w', encoding='utf-8') as f:
    json.dump(data_payload, f, separators=(',', ':'))

print(f"Done! Cleaned venues list has {len(venues_list)} venues, {len(opponents_list)} opponents, and computed benchmarks.")
