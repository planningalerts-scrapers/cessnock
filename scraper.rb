require "masterview_scraper"

MasterviewScraper.scrape_and_save_period(
  url: "http://datracker.cessnock.nsw.gov.au",
  period: :last10days,
  use_api: true
)
