"use strict";

const { faker } = require("@faker-js/faker");

module.exports = {
  up: async (queryInterface, Sequelize) => {
    const avatarsData = Array.from({ length: 10 }).map((_, index) => ({
      id: index + 1,
      name: faker.internet.userName(),
      filename: faker.system.fileName(),
      created_at: new Date(),
      updated_at: new Date(),
    }));

    return queryInterface.bulkInsert("avatars", avatarsData);
  },

  down: async (queryInterface, Sequelize) => {
    return queryInterface.bulkDelete("avatars", null, {});
  },
};
